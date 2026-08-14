<?php

namespace App\Domain\Cart\Actions;

use App\Models\Cart;
use App\Support\GuestContext;
use Illuminate\Http\Request;

class ResolveGuestCart
{
    public function __construct(private GuestContext $guest) {}

    public function handle(Request $request, bool $create = true): array
    {
        $token = $this->guest->token($request);
        $cart = $token ? Cart::query()->where('access_token_hash', $this->guest->hash($token))->where('status', 'active')->where('expires_at', '>', now())->first() : null;
        if (! $cart && $request->user()) {
            $cart = Cart::query()->where('user_id', $request->user()->id)->where('status', 'active')->where('expires_at', '>', now())->latest()->first();
        }
        if (! $cart && $create) {
            $token = $token ?: $this->guest->tokenOrCreate($request);
            $cart = Cart::query()->create(['user_id' => $request->user()?->id, 'access_token_hash' => $this->guest->hash($token), 'status' => 'active', 'currency' => config('commerce.currency'), 'expires_at' => now()->addDays(config('commerce.cart_expiry_days'))]);
        }

        return [$cart, $token];
    }
}
