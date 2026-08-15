<?php

namespace App\Domain\Cart\Actions;

use App\Models\Cart;
use Illuminate\Http\Request;

class ResolveCheckoutContactPrefill
{
    /**
     * Priority per field: old() validation input -> the cart's pending/current Order
     * snapshot (if one exists) -> the authenticated customer's saved profile defaults
     * -> User.email -> empty. For guests, the profile step is always null and the
     * chain collapses to old() -> pending order -> empty, matching prior behaviour.
     */
    public function handle(Request $request, Cart $cart): array
    {
        $pendingAddress = $cart->convertedOrder?->shipping_address;
        $pendingEmail = $cart->convertedOrder?->email;
        $pendingPhone = $cart->convertedOrder?->phone;
        $profile = $request->user()?->customerProfile;
        $profileAddress = $profile?->default_shipping_address;

        $field = fn (string $key, mixed $pending, mixed $fallback) => old($key, $pending ?? $fallback);

        return [
            'first_name' => $field('first_name', $pendingAddress['first_name'] ?? null, $profile?->first_name),
            'last_name' => $field('last_name', $pendingAddress['last_name'] ?? null, $profile?->last_name),
            'email' => $field('email', $pendingEmail, $request->user()?->email),
            'phone' => $field('phone', $pendingPhone, $profile?->phone),
            'address_line_1' => $field('address_line_1', $pendingAddress['address_line_1'] ?? null, $profileAddress['address_line_1'] ?? null),
            'address_line_2' => $field('address_line_2', $pendingAddress['address_line_2'] ?? null, $profileAddress['address_line_2'] ?? null),
            'city' => $field('city', $pendingAddress['city'] ?? null, $profileAddress['city'] ?? null),
            'county' => $field('county', $pendingAddress['county'] ?? null, $profileAddress['county'] ?? null),
            'postcode' => $field('postcode', $pendingAddress['postcode'] ?? null, $profileAddress['postcode'] ?? null),
        ];
    }
}
