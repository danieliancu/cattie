<?php

namespace App\Domain\Payments\Actions;

use App\Models\Cart;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

final class ResolveEligibleShippingMethods
{
    /** @return Collection<int, ShippingMethod> */
    public function handle(Cart $cart, string $country = 'GB'): Collection
    {
        $cart->loadMissing('items.variant.fulfilmentMappings');
        if ($cart->items->isEmpty()) {
            return collect();
        }

        $providers = $cart->items->map(function ($item) {
            $mappings = $item->variant?->fulfilmentMappings?->where('is_active', true) ?? collect();

            return $mappings->count() === 1 ? $mappings->first()->provider : null;
        });
        if ($providers->contains(null) || $providers->unique()->count() !== 1) {
            return collect();
        }

        return ShippingMethod::query()->active()
            ->where('provider', $providers->first())
            ->where('country', strtoupper($country))
            ->where('currency', $cart->currency)
            ->ordered()->get();
    }
}
