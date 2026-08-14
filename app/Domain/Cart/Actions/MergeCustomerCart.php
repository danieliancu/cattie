<?php

namespace App\Domain\Cart\Actions;

use App\Models\Cart;
use App\Models\User;
use App\Support\GuestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MergeCustomerCart
{
    public function __construct(private GuestContext $guest, private AbandonCartCheckout $abandonCheckout, private RefreshCartPrices $prices) {}

    public function handle(Request $request, User $user): ?Cart
    {
        $token = $this->guest->token($request);
        $browserCart = $token ? Cart::query()
            ->where('access_token_hash', $this->guest->hash($token))
            ->where('status', 'active')->where('expires_at', '>', now())->first() : null;
        $primary = $browserCart ?? Cart::query()->where('user_id', $user->id)
            ->where('status', 'active')->where('expires_at', '>', now())->latest()->first();

        if (! $primary) {
            return null;
        }

        $secondary = Cart::query()->where('user_id', $user->id)->where('status', 'active')
            ->whereKeyNot($primary->id)->get();
        foreach ($secondary as $cart) {
            $this->abandonCheckout->handle($cart);
        }

        DB::transaction(function () use ($primary, $secondary, $user) {
            $primary = Cart::query()->lockForUpdate()->findOrFail($primary->id);
            $primary->update(['user_id' => $user->id]);
            foreach ($secondary as $cart) {
                $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
                $cart->items()->update(['cart_id' => $primary->id]);
                $cart->update(['status' => 'merged', 'expires_at' => now(), 'converted_order_id' => null]);
            }
            $sessionIds = $primary->items()->whereNotNull('artwork_session_id')->pluck('artwork_session_id');
            if ($sessionIds->isNotEmpty()) {
                \App\Models\ArtworkSession::query()->whereIn('id', $sessionIds)->whereNull('user_id')->update(['user_id' => $user->id]);
                \App\Models\Upload::query()->whereIn('artwork_session_id', $sessionIds)->whereNull('user_id')->update(['user_id' => $user->id]);
            }
        });

        $primary = $primary->fresh('items');
        $this->prices->handle($primary);

        return $primary->fresh('items');
    }
}
