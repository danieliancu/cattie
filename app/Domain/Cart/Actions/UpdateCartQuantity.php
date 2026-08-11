<?php

namespace App\Domain\Cart\Actions;

use App\Models\CartItem;
use Illuminate\Validation\ValidationException;

class UpdateCartQuantity
{
    public function handle(CartItem $item, int $quantity): void
    {
        if ($quantity < 1 || $quantity > config('commerce.max_quantity')) {
            throw ValidationException::withMessages(['quantity' => 'Choose a quantity between 1 and '.config('commerce.max_quantity').'.']);
        }$item->update(['quantity' => $quantity]);
        app(RefreshCartPrices::class)->handle($item->cart);
    }
}
