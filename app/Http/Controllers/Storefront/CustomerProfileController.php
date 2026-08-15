<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Customer\Actions\UpdateCustomerProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UkPostcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CustomerProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $values = $this->currentValues($request->user());

        return view('storefront.account.details', compact('values'));
    }

    public function update(Request $request, UpdateCustomerProfile $update): JsonResponse
    {
        if ($request->filled('postcode')) {
            $request->merge(['postcode' => UkPostcode::normalizeForInput($request->input('postcode'))]);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'county' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postcode' => ['sometimes', 'nullable', 'regex:'.UkPostcode::FORMAT_REGEX],
        ]);

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower(trim($data['email']));
        }

        $update->handle($request->user(), $data);

        return response()->json(['status' => 'saved', 'values' => $this->currentValues($request->user()->fresh())]);
    }

    private function currentValues(User $user): array
    {
        $profile = $user->customerProfile;
        $address = $profile?->default_shipping_address ?? [];

        return [
            'first_name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'email' => $user->email,
            'phone' => $profile?->phone,
            'address_line_1' => $address['address_line_1'] ?? null,
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'] ?? null,
            'county' => $address['county'] ?? null,
            'postcode' => $address['postcode'] ?? null,
        ];
    }
}
