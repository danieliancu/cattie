<?php

namespace App\Http\Controllers\Storefront;

use App\Data\AddressLookupAddress;
use App\Http\Controllers\Controller;
use App\Providers\AddressLookup\GooglePlacesAddressLookupProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AddressAutocompleteController extends Controller
{
    /**
     * Free-text "type your address" suggestions, e.g. GET ?q=10 Downing Street.
     * Never surfaces provider errors — a failed/unavailable lookup just returns
     * an empty suggestion list, leaving manual entry fully available.
     */
    public function suggest(Request $request, GooglePlacesAddressLookupProvider $provider): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:200']]);

        return response()->json(['suggestions' => $provider->suggest($data['q'])]);
    }

    /**
     * Resolves a chosen suggestion into structured address fields.
     */
    public function resolve(string $placeId, GooglePlacesAddressLookupProvider $provider): JsonResponse
    {
        $address = $provider->resolve($placeId);

        if (! $address instanceof AddressLookupAddress) {
            return response()->json(['resolved' => false]);
        }

        return response()->json([
            'resolved' => true,
            'address_line_1' => $address->addressLine1,
            'address_line_2' => $address->addressLine2,
            'city' => $address->city,
            'county' => $address->county,
            'postcode' => $address->postcode,
            'country' => $address->country,
        ]);
    }
}
