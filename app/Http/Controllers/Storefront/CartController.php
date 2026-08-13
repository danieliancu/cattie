<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Cart\Actions\AddApprovedArtworkToCart;
use App\Domain\Cart\Actions\RefreshCartPrices;
use App\Domain\Cart\Actions\ResolveGuestCart;
use App\Domain\Cart\Actions\UpdateCartQuantity;
use App\Enums\ArtworkSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ArtworkSession;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ComposedDesign;
use App\Models\GenerationAsset;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function add(string $publicId, Request $request, ResolveGuestCart $resolve, AddApprovedArtworkToCart $add, ApproveArtwork $approve, RenderComposedDesign $render, GuestContext $guest): RedirectResponse
    {
        $session = ArtworkSession::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless($guest->owns($session->access_token_hash, $request), 404);
        if ($session->status === ArtworkSessionStatus::PreviewReady) {
            $session->load(['product.designTemplate', 'currentGeneration.assets', 'composedDesigns']);
            $selection = $request->validate(['asset_id' => ['nullable', 'string'], 'design_id' => ['nullable', 'string']]);
            $asset = isset($selection['asset_id'])
                ? GenerationAsset::query()->whereKey($selection['asset_id'])->whereHas('generation', fn ($query) => $query->where('artwork_session_id', $session->id))->firstOrFail()
                : ($session->product->designTemplate
                    ? $session->currentGeneration?->assets->firstWhere('kind', 'provider_original')
                    : ($session->currentGeneration?->assets->firstWhere('kind', 'web_preview') ?? $session->currentGeneration?->assets->firstWhere('kind', 'provider_original')));
            $design = $session->product->designTemplate
                ? (isset($selection['design_id'])
                    ? ComposedDesign::query()->whereKey($selection['design_id'])->where('artwork_session_id', $session->id)->firstOrFail()
                    : $session->composedDesigns->where('generation_asset_id', $asset?->id)->where('product_variant_id', $session->product_variant_id)->sortByDesc('created_at')->first())
                : null;
            abort_unless($asset, 422);
            if ($session->product->designTemplate) {
                $asset = $session->currentGeneration?->assets->firstWhere('kind', 'composition_source')
                    ?? $session->currentGeneration?->assets->firstWhere('kind', 'provider_original');
                abort_unless($asset, 422);
                $design = $render->handle($session->fresh(), $asset, $design?->character_adjustments ?? []);
            }
            $approve->handle($session, $asset, $design);
            $session->refresh();
        }
        [$cart, $token] = $resolve->handle($request);
        $add->handle($cart, $session);

        return redirect()->route('cart.index')->withCookie($guest->cookie($token));
    }

    public function index(Request $request, ResolveGuestCart $resolve, RefreshCartPrices $prices): View
    {
        [$cart] = $resolve->handle($request, false);
        if ($cart) {
            $prices->handle($cart);
            $cart->load(['items.artworkSession', 'items.generationAsset', 'items.composedDesign', 'items.variant']);
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
        $session = $item->artworkSession()->with('product')->firstOrFail();
        $item->delete();
        $session->update([
            'status' => ArtworkSessionStatus::PreviewReady,
            'expires_at' => now()->addDays(config('artwork.retention_days')),
        ]);
        app(RefreshCartPrices::class)->handle($cart);

        return redirect()->route('products.show', $session->product->slug);
    }

    private function ownedCart(Request $request, ResolveGuestCart $resolve): Cart
    {
        [$cart] = $resolve->handle($request, false);
        abort_unless($cart, 404);

        return $cart;
    }
}
