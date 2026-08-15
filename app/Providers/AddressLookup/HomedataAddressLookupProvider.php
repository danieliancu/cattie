<?php

namespace App\Providers\AddressLookup;

use App\Contracts\AddressLookupProvider;
use App\Data\AddressLookupAddress;
use App\Data\AddressLookupResult;
use App\Support\UkPostcode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HomedataAddressLookupProvider implements AddressLookupProvider
{
    /**
     * Property-level lookup via Homedata's "Postcode Lookup" endpoint
     * (GET /address/postcode/{postcode}/). Free tier: 100 calls/month, no card.
     * https://homedata.co.uk/docs/endpoints
     */
    public function lookup(string $canonicalPostcode): AddressLookupResult
    {
        $apiKey = config('address_lookup.homedata.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            Log::warning('Homedata lookup skipped: no API key configured.', ['operation' => 'GET /address/postcode/{postcode}']);

            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        $spaceless = UkPostcode::normalizeForInput($canonicalPostcode);

        try {
            $response = Http::baseUrl((string) config('address_lookup.homedata.base_url'))
                ->connectTimeout((int) config('address_lookup.homedata.connect_timeout'))
                ->timeout((int) config('address_lookup.homedata.timeout'))
                ->withHeaders(['Authorization' => 'Api-Key '.$apiKey])
                ->acceptJson()
                ->get('/address/postcode/'.rawurlencode($spaceless).'/');
        } catch (ConnectionException) {
            Log::warning('Homedata lookup failed: connection error.', ['operation' => 'GET /address/postcode/{postcode}']);

            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        if ($response->failed()) {
            Log::warning('Homedata lookup failed.', ['operation' => 'GET /address/postcode/{postcode}', 'status' => $response->status()]);

            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        $body = $response->json();
        $entries = is_array($body['addresses'] ?? null) ? $body['addresses'] : [];
        $postcode = is_string($body['postcode'] ?? null) ? $body['postcode'] : UkPostcode::format($spaceless);

        return new AddressLookupResult(
            valid: true,
            postcode: $postcode,
            addresses: array_values(array_filter(array_map(
                fn (array $entry) => $this->normalize($entry, $postcode),
                array_filter($entries, 'is_array'),
            ))),
        );
    }

    private function normalize(array $entry, string $postcode): ?AddressLookupAddress
    {
        $subBuilding = trim((string) ($entry['sub_building'] ?? ''));
        $buildingName = trim((string) ($entry['building_name'] ?? ''));
        $buildingNumber = trim((string) ($entry['building_number'] ?? ''));
        $street = trim((string) ($entry['street'] ?? ''));
        $town = trim((string) ($entry['town'] ?? ''));

        $streetLine = trim(implode(' ', array_filter([$buildingNumber, $street])));
        $nameLine = collect([$subBuilding, $buildingName])->filter(fn ($v) => $v !== '')->implode(', ');

        $line1 = $nameLine !== '' ? $nameLine : $streetLine;
        $line2 = $nameLine !== '' ? ($streetLine !== '' ? $streetLine : null) : null;

        if ($line1 === '' || $town === '') {
            return null;
        }

        return new AddressLookupAddress(
            addressLine1: Str::squish($line1),
            addressLine2: $line2 !== null ? Str::squish($line2) : null,
            city: Str::squish($town),
            county: null,
            postcode: $postcode,
        );
    }
}
