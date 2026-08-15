<?php

namespace App\Providers\AddressLookup;

use App\Contracts\AddressLookupProvider;
use App\Data\AddressLookupAddress;
use App\Data\AddressLookupResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GooglePlacesAddressLookupProvider implements AddressLookupProvider
{
    private const BASE_URL = 'https://places.googleapis.com';

    private const MAX_ADDRESSES = 5;

    /**
     * Postcode-only lookup, kept for AddressLookupProvider interface compliance /
     * config-swappability. Google's Autocomplete resolves a bare postcode to the
     * postal-code AREA as a place, not individual properties within it — so this
     * path realistically returns zero addresses. The free-text suggest()/resolve()
     * pair below is what the "type your address" UI actually uses for Google.
     */
    public function lookup(string $canonicalPostcode): AddressLookupResult
    {
        $suggestions = $this->suggest($canonicalPostcode);
        $addresses = collect($suggestions)
            ->take(self::MAX_ADDRESSES)
            ->map(fn (array $s) => $this->resolve($s['id']))
            ->filter()
            ->values()
            ->all();

        return new AddressLookupResult(valid: true, postcode: $canonicalPostcode, addresses: $addresses);
    }

    /**
     * Free-text address autocomplete. Returns up to MAX_ADDRESSES suggestions as
     * [{"id": placeId, "description": "10 Downing Street, London, UK"}, ...].
     */
    public function suggest(string $query): array
    {
        $apiKey = config('address_lookup.google_places.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '' || trim($query) === '') {
            return [];
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->connectTimeout((int) config('address_lookup.google_places.connect_timeout'))
                ->timeout((int) config('address_lookup.google_places.timeout'))
                ->withHeaders(['X-Goog-Api-Key' => $apiKey, 'Content-Type' => 'application/json'])
                ->post('/v1/places:autocomplete', [
                    'input' => $query,
                    'includedRegionCodes' => ['gb'],
                ]);
        } catch (ConnectionException) {
            Log::warning('Google Places autocomplete failed: connection error.', ['operation' => 'places:autocomplete']);

            return [];
        }

        if ($response->failed()) {
            Log::warning('Google Places autocomplete failed.', ['operation' => 'places:autocomplete', 'status' => $response->status()]);

            return [];
        }

        return collect($response->json('suggestions') ?? [])
            ->map(fn ($s) => [
                'id' => $s['placePrediction']['placeId'] ?? null,
                'description' => $s['placePrediction']['text']['text'] ?? null,
            ])
            ->filter(fn ($s) => is_string($s['id']) && $s['id'] !== '' && is_string($s['description']) && $s['description'] !== '')
            ->take(self::MAX_ADDRESSES)
            ->values()
            ->all();
    }

    /**
     * Resolves a chosen place prediction into a normalized, structured UK address.
     */
    public function resolve(string $placeId): ?AddressLookupAddress
    {
        $apiKey = config('address_lookup.google_places.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->connectTimeout((int) config('address_lookup.google_places.connect_timeout'))
                ->timeout((int) config('address_lookup.google_places.timeout'))
                ->withHeaders(['X-Goog-Api-Key' => $apiKey, 'X-Goog-FieldMask' => 'addressComponents'])
                ->get('/v1/places/'.$placeId);
        } catch (ConnectionException) {
            Log::warning('Google Places details failed: connection error.', ['operation' => 'places:details']);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Google Places details failed.', ['operation' => 'places:details', 'status' => $response->status()]);

            return null;
        }

        $components = collect($response->json('addressComponents') ?? []);
        $find = fn (string $type) => $components->first(fn ($component) => in_array($type, $component['types'] ?? [], true))['longText'] ?? null;

        $streetLine = trim(implode(' ', array_filter([$find('street_number'), $find('route')])));
        $subpremise = $find('subpremise');
        $city = $find('postal_town') ?? $find('locality');
        $county = $find('administrative_area_level_2');
        $postcode = $find('postal_code');

        $line1 = $subpremise ? Str::squish($subpremise) : ($streetLine !== '' ? Str::squish($streetLine) : null);
        $line2 = $subpremise && $streetLine !== '' ? Str::squish($streetLine) : null;

        if (! $line1 || ! $city) {
            return null;
        }

        return new AddressLookupAddress(
            addressLine1: $line1,
            addressLine2: $line2,
            city: Str::squish($city),
            county: $county ? Str::squish($county) : null,
            postcode: $postcode ? Str::squish($postcode) : '',
        );
    }
}
