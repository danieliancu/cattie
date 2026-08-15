<?php

namespace App\Providers\AddressLookup;

use App\Contracts\AddressLookupProvider;
use App\Data\AddressLookupResult;
use App\Support\UkPostcode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostcodesIoAddressLookupProvider implements AddressLookupProvider
{
    /**
     * Postcodes.io validates and normalizes UK postcodes but does not provide
     * property-level address data. `addresses` is always empty for this provider —
     * never fabricate address lines from locality metadata.
     */
    public function lookup(string $canonicalPostcode): AddressLookupResult
    {
        $spaceless = UkPostcode::normalizeForInput($canonicalPostcode);

        try {
            $response = Http::baseUrl((string) config('address_lookup.postcodes_io.base_url'))
                ->connectTimeout((int) config('address_lookup.postcodes_io.connect_timeout'))
                ->timeout((int) config('address_lookup.postcodes_io.timeout'))
                ->acceptJson()
                ->get('/postcodes/'.rawurlencode($spaceless));
        } catch (ConnectionException) {
            Log::warning('Postcodes.io lookup failed: connection error.', ['operation' => 'GET /postcodes/{postcode}']);

            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        if ($response->status() === 404) {
            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        if ($response->failed()) {
            Log::warning('Postcodes.io lookup failed.', ['operation' => 'GET /postcodes/{postcode}', 'status' => $response->status()]);

            return new AddressLookupResult(valid: false, postcode: $canonicalPostcode);
        }

        $result = $response->json('result');
        $result = is_array($result) ? $result : [];

        return new AddressLookupResult(
            valid: true,
            postcode: $canonicalPostcode,
            addresses: [],
            metadata: array_filter([
                'region' => $result['region'] ?? null,
                'admin_district' => $result['admin_district'] ?? null,
                'nation' => $result['country'] ?? null,
            ]),
        );
    }
}
