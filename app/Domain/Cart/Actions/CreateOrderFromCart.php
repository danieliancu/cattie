<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderFromCart
{
    public function __construct(
        private RefreshCartPrices $prices,
        private TransitionOrder $transition,
        private RecordAnalyticsEvent $analytics,
    ) {}

    public function handle(Cart $cart, string $pricingHash, array $customer, string $idempotencyKey): Order
    {
        return DB::transaction(function () use ($cart, $pricingHash, $customer, $idempotencyKey) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);

            if ($cart->converted_order_id) {
                return Order::query()->findOrFail($cart->converted_order_id);
            }

            $cart->load(['items.artworkSession.approvedAsset.generation', 'items.product', 'items.variant']);
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your basket is empty.']);
            }

            $previousHash = $cart->pricing_hash;
            $currentHash = $this->prices->handle($cart);
            if (! hash_equals((string) $previousHash, $pricingHash) || ! hash_equals((string) $previousHash, $currentHash)) {
                throw ValidationException::withMessages(['pricing_hash' => 'Your basket price has changed. Please review it before continuing.']);
            }

            foreach ($cart->items as $item) {
                $session = $item->artworkSession;
                if (! $session || $session->status !== ArtworkSessionStatus::Approved ||
                    $session->approved_generation_asset_id !== $item->generation_asset_id ||
                    $session->approvedAsset?->generation?->status !== GenerationStatus::Succeeded ||
                    ! $item->product?->is_active || ! $item->variant?->is_active) {
                    throw ValidationException::withMessages(['cart' => 'One of your basket items is no longer available.']);
                }
            }

            $subtotal = $cart->items->sum(fn ($item) => $item->lineTotalMinor());
            $order = Order::query()->create([
                'number' => $this->orderNumber(), 'access_token_hash' => $cart->access_token_hash,
                'checkout_idempotency_key' => $idempotencyKey, 'email' => $customer['email'],
                'phone' => $customer['phone'] ?? null, 'status' => OrderStatus::Draft,
                'currency' => $cart->currency, 'subtotal_minor' => $subtotal, 'discount_minor' => 0,
                'shipping_minor' => null, 'tax_minor' => null, 'total_minor' => null,
                'shipping_status' => 'unresolved', 'tax_status' => 'unresolved',
                'totals_status' => 'unresolved', 'is_payable' => false,
                'shipping_address' => $customer['shipping_address'], 'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'artwork_session_id' => $item->artwork_session_id, 'generation_id' => $item->generation_id,
                    'generation_asset_id' => $item->generation_asset_id, 'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id, 'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name, 'artwork_style_name' => $item->artwork_style_name,
                    'sku' => $item->variant->sku, 'personalisation' => $item->personalisation,
                    'artwork_snapshot' => ['generation_asset_id' => $item->generation_asset_id],
                    'quantity' => $item->quantity, 'unit_price_minor' => $item->unit_price_minor,
                    'total_price_minor' => $item->lineTotalMinor(), 'currency' => $item->currency,
                ]);
            }

            $order = $this->transition->handle($order, OrderStatus::AwaitingPayment, reason: 'Checkout details accepted');
            $cart->update(['status' => 'converted', 'converted_order_id' => $order->id]);
            $this->analytics->handle('order_created', $order);
            $this->analytics->handle('awaiting_payment', $order);

            return $order->load('items');
        });
    }

    private function orderNumber(): string
    {
        do {
            $number = 'CAT-'.now()->format('ym').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
