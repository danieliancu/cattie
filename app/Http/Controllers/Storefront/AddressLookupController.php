<?php

namespace App\Http\Controllers\Storefront;

use App\Contracts\AddressLookupProvider;
use App\Data\AddressLookupAddress;
use App\Http\Controllers\Controller;
use App\Support\UkPostcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class AddressLookupController extends Controller
{
    public function __invoke(Request $request, AddressLookupProvider $provider): JsonResponse
    {
        $data = $request->validate(['postcode' => ['required', 'string']]);
        // Cap defensively before normalizing/matching: any genuine UK postcode is well under
        // this length, and this keeps obviously-garbage input on the graceful valid:false path
        // instead of throwing a raw validation error for what is just a mistyped postcode.
        $normalized = UkPostcode::normalizeForInput(substr($data['postcode'], 0, 16));

        if (! UkPostcode::isValidFormat($normalized)) {
            return response()->json(['valid' => false, 'postcode' => null, 'addresses' => []]);
        }

        $canonical = UkPostcode::format($normalized);

        try {
            $result = $provider->lookup($canonical);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['valid' => false, 'postcode' => $canonical, 'addresses' => []]);
        }

        return response()->json([
            'valid' => $result->valid,
            'postcode' => $result->postcode,
            'addresses' => array_map(fn (AddressLookupAddress $address) => [
                'address_line_1' => $address->addressLine1,
                'address_line_2' => $address->addressLine2,
                'city' => $address->city,
                'county' => $address->county,
                'postcode' => $address->postcode,
                'country' => $address->country,
            ], $result->addresses),
        ]);
    }
}
