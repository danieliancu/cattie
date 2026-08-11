<?php

namespace App\Domain\Cart\Actions;

use App\Models\Cart;

class RefreshCartPrices
{
    public function handle(Cart $cart): string
    {
        $cart->load('items.variant');
        foreach ($cart->items as $item) {
            if ($item->variant?->is_active && $item->variant->price_minor !== $item->unit_price_minor) {
                $item->update(['unit_price_minor' => $item->variant->price_minor, 'currency' => $item->variant->currency]);
            }
        }$hash = hash('sha256', $cart->items->sortBy('id')->map(fn ($item) => implode(':', [$item->id, $item->unit_price_minor, $item->quantity, $item->updated_at?->getTimestamp()]))->implode('|'));
        $cart->update(['pricing_hash' => $hash, 'expires_at' => now()->addDays(config('commerce.cart_expiry_days'))]);

        return $hash;
    }
}
