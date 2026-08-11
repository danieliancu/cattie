<?php

namespace App\Domain\Payments\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;

class OrderPayability
{
    public function check(Order $order): bool
    {
        $order->loadMissing(['items.artworkSession.product.designTemplate', 'items.generation', 'items.generationAsset', 'items.composedDesign']);
        $address = $order->shipping_address ?? [];
        $requiredAddress = ['first_name', 'last_name', 'address_line_1', 'city', 'postcode', 'country'];

        return $order->status === OrderStatus::AwaitingPayment
            && $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->artworkSession?->status === ArtworkSessionStatus::Approved
                && $item->artworkSession?->approved_generation_asset_id === $item->generation_asset_id
                && $item->generation?->status === GenerationStatus::Succeeded
                && $item->generationAsset !== null
                && (! $item->artworkSession?->product?->designTemplate || ($item->composedDesign !== null && $item->artworkSession?->approved_composed_design_id === $item->composed_design_id))
                && $item->currency === $order->currency)
            && $order->currency === 'GBP'
            && $order->subtotal_minor >= 0
            && $order->shipping_status === 'resolved' && $order->shipping_minor !== null
            && $order->tax_status === 'resolved' && $order->tax_minor !== null
            && $order->totals_status === 'resolved' && $order->total_minor !== null
            && collect($requiredAddress)->every(fn ($key) => filled($address[$key] ?? null))
            && $address['country'] === 'GB'
            && $order->total_minor === $order->subtotal_minor + $order->shipping_minor + $order->tax_minor - $order->discount_minor;
    }
}
