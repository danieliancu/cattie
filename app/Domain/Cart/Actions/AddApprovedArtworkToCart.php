<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\Cart;
use Illuminate\Validation\ValidationException;

class AddApprovedArtworkToCart
{
    public function __construct(private RefreshCartPrices $prices, private RecordAnalyticsEvent $analytics) {}

    public function handle(Cart $cart, ArtworkSession $session)
    {
        $session->load(['product.designTemplate', 'variant', 'artworkStyle', 'approvedAsset.generation', 'approvedComposedDesign']);
        if ($session->status !== ArtworkSessionStatus::Approved || ! $session->approvedAsset || $session->approvedAsset->generation->status !== GenerationStatus::Succeeded) {
            throw ValidationException::withMessages(['artwork' => 'Approve your artwork before adding it to your basket.']);
        }
        if ($session->product->designTemplate && (! $session->approvedComposedDesign || $session->approvedComposedDesign->generation_asset_id !== $session->approved_generation_asset_id)) {
            throw ValidationException::withMessages(['artwork' => 'Approve your product design before adding it to your basket.']);
        }
        if (! $session->product->is_active || ! $session->variant?->is_active) {
            throw ValidationException::withMessages(['artwork' => 'This product option is no longer available.']);
        }$item = $cart->items()->firstOrCreate(['artwork_session_id' => $session->id], ['product_id' => $session->product_id, 'product_variant_id' => $session->product_variant_id, 'generation_id' => $session->approvedAsset->generation_id, 'generation_asset_id' => $session->approved_generation_asset_id, 'composed_design_id' => $session->approved_composed_design_id, 'product_name' => $session->product->name, 'variant_name' => $session->variant->name, 'artwork_style_name' => $session->artworkStyle->name, 'personalisation' => $session->personalisation_snapshot, 'quantity' => 1, 'unit_price_minor' => $session->variant->price_minor, 'currency' => $session->variant->currency]);
        $this->prices->handle($cart);
        if ($item->wasRecentlyCreated) {
            $this->analytics->handle('add_to_cart', $item);
        }

        return $item;
    }
}
