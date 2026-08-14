<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Providers\Payments\StripePaymentProvider;
use Illuminate\Validation\ValidationException;

final class StartEmbeddedCheckout
{
    public function __construct(
        private StartPayment $startPayment,
        private StripePaymentProvider $stripe,
        private ReconcilePayment $reconcile,
    ) {}

    public function handle(Order $order, string $idempotencyKey): array
    {
        $existing = $order->payments()
            ->where('provider', 'stripe')
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('external_id')
            ->latest()
            ->first();

        if ($existing) {
            $result = $this->stripe->retrieve($existing->external_id);
            if ($result->status === PaymentStatus::Pending && $result->clientSecret) {
                return $this->response($existing, $result);
            }

            $this->reconcile->handle($result, $existing);
            if ($result->status === PaymentStatus::Succeeded) {
                return ['payment' => $existing->refresh(), 'client_secret' => null];
            }
        }

        $payment = $this->startPayment->handle($order->fresh('items'), $idempotencyKey);
        if ($payment->status !== PaymentStatus::Pending || ! $payment->external_id) {
            return ['payment' => $payment, 'client_secret' => null];
        }

        return $this->response($payment, $this->stripe->retrieve($payment->external_id));
    }

    private function response(Payment $payment, PaymentResult $result): array
    {
        if ($result->status !== PaymentStatus::Pending || ! $result->clientSecret) {
            throw ValidationException::withMessages(['payment' => "We couldn't start your payment. Please try again."]);
        }

        return ['payment' => $payment->refresh(), 'client_secret' => $result->clientSecret];
    }
}
