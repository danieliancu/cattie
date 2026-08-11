<?php

namespace App\Domain\Orders\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionOrder
{
    private const ALLOWED = [
        'draft' => ['personalising', 'awaiting_payment', 'cancelled'],
        'personalising' => ['generating_artwork', 'awaiting_approval', 'cancelled'],
        'generating_artwork' => ['awaiting_approval', 'generation_failed', 'cancelled'],
        'generation_failed' => ['generating_artwork', 'cancelled'],
        'awaiting_approval' => ['approved', 'generating_artwork', 'cancelled'],
        'approved' => ['awaiting_payment', 'cancelled'],
        'awaiting_payment' => ['paid', 'payment_failed', 'cancelled'],
        'payment_failed' => ['awaiting_payment', 'cancelled'],
        'paid' => ['preparing_print_asset', 'refunded'],
        'preparing_print_asset' => ['submitted_to_fulfilment', 'fulfilment_failed', 'refunded'],
        'fulfilment_failed' => ['preparing_print_asset', 'submitted_to_fulfilment', 'refunded'],
        'submitted_to_fulfilment' => ['in_production', 'fulfilment_failed', 'cancelled', 'refunded'],
        'in_production' => ['shipped', 'fulfilment_failed', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
    ];

    public function handle(Order $order, OrderStatus $to, ?int $actorId = null, ?string $reason = null, array $metadata = []): Order
    {
        return DB::transaction(function () use ($order, $to, $actorId, $reason, $metadata) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $from = $order->status;

            if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Order cannot move from {$from->value} to {$to->value}."]);
            }

            $order->update(['status' => $to]);
            $order->transitions()->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'metadata' => $metadata ?: null]);

            return $order->refresh();
        });
    }
}
