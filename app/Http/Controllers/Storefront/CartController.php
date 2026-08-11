<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Actions\AddApprovedArtworkToCart;
use App\Domain\Cart\Actions\RefreshCartPrices;
use App\Domain\Cart\Actions\ResolveGuestCart;
use App\Domain\Cart\Actions\UpdateCartQuantity;
use App\Http\Controllers\Controller;
use App\Models\ArtworkSession;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function add(string $publicId, Request $request, ResolveGuestCart $resolve, AddApprovedArtworkToCart $add, GuestContext $guest): RedirectResponse
    {
        $session = ArtworkSession::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless($guest->owns($session->access_token_hash, $request), 404);
        [$cart, $token] = $resolve->handle($request);
        $add->handle($cart, $session);

        return redirect()->route('cart.index')->withCookie($guest->cookie($token));
    }

    public function index(Request $request, ResolveGuestCart $resolve, RefreshCartPrices $prices): View
    {
        [$cart] = $resolve->handle($request, false);
        if ($cart) {
            $prices->handle($cart);
            $cart->load(['items.artworkSession', 'items.generationAsset', 'items.variant']);
        }

        return view('storefront.cart.index', compact('cart'));
    }

    public function quantity(CartItem $item, Request $request, ResolveGuestCart $resolve, UpdateCartQuantity $update): RedirectResponse
    {
        $cart = $this->ownedCart($request, $resolve);
        abort_unless($item->cart_id === $cart->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.config('commerce.max_quantity')]]);
        $update->handle($item, (int) $data['quantity']);

        return back();
    }

    public function remove(CartItem $item, Request $request, ResolveGuestCart $resolve): RedirectResponse
    {
        $cart = $this->ownedCart($request, $resolve);
        abort_unless($item->cart_id === $cart->id, 404);
        $item->delete();
        app(RefreshCartPrices::class)->handle($cart);

        return back();
    }

    public function changeArtwork(CartItem $item, Request $request, ResolveGuestCart $resolve): RedirectResponse
    {
        $cart = $this->ownedCart($request, $resolve);
        abort_unless($item->cart_id === $cart->id, 404);
        $publicId = $item->artworkSession()->value('public_id');
        $item->delete();
        app(RefreshCartPrices::class)->handle($cart);

        return redirect()->route('artwork.show', $publicId);
    }

    private function ownedCart(Request $request, ResolveGuestCart $resolve): Cart
    {
        [$cart] = $resolve->handle($request, false);
        abort_unless($cart, 404);

        return $cart;
    }
}
