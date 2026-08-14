<?php

namespace App\Domain\Cart\Actions;

use App\Contracts\PaymentProvider;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AbandonCartCheckout
{
    public function __construct(private PaymentProvider $payments, private TransitionOrder $transition) {}

    public function handle(Cart $cart): void
    {
        $order = $cart->convertedOrder()->with('payments')->first();
        if (! $order) {
            return;
        }
        if ($order->status === OrderStatus::Paid) {
            throw ValidationException::withMessages(['cart' => 'A paid order can no longer be changed.']);
        }

        foreach ($order->payments as $payment) {
            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::RequiresAction], true) || ! $payment->external_id) {
                continue;
            }
            try {
                $this->payments->cancel($payment->external_id);
            } catch (Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['cart' => 'We could not reopen your basket safely. Please try again.']);
            }
        }

        DB::transaction(function () use ($cart, $order) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === OrderStatus::Paid) {
                throw ValidationException::withMessages(['cart' => 'A paid order can no longer be changed.']);
            }
            if (in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::PaymentFailed, OrderStatus::Draft], true)) {
                $this->transition->handle($order, OrderStatus::Cancelled, reason: 'Basket edited before payment completion');
            }
            $order->payments()
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::RequiresAction->value])
                ->update(['status' => PaymentStatus::Cancelled->value, 'failure_code' => 'basket_changed', 'failure_reason' => 'Basket changed before payment completion.', 'completed_at' => now()]);
            $order->update(['is_payable' => false]);
            $cart->update(['status' => 'active', 'converted_order_id' => null]);
        });
    }
}
