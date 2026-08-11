<?php

namespace App\Domain\Payments\Actions;

use App\Contracts\PaymentProvider;
use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Payments\Data\PaymentRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartPayment
{
    public function __construct(
        private PaymentProvider $provider,
        private OrderPayability $payability,
        private CompleteSuccessfulPayment $complete,
        private RecordAnalyticsEvent $analytics,
    ) {}

    public function handle(Order $order, string $idempotencyKey, string $scenario): Payment
    {
        return DB::transaction(function () use ($order, $idempotencyKey, $scenario) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless($existing->order_id === $order->id, 409);

                return $existing;
            }

            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== OrderStatus::AwaitingPayment || ! $this->payability->check($order)) {
                throw ValidationException::withMessages(['payment' => 'This order is not ready for payment.']);
            }

            $payment = $order->payments()->create(['provider' => config('payments.provider'), 'idempotency_key' => $idempotencyKey,
                'status' => PaymentStatus::Pending, 'amount_minor' => $order->total_minor, 'currency' => $order->currency]);
            $this->analytics->handle('payment_started', $payment);

            try {
                $result = $this->provider->create(new PaymentRequest($order->id, $order->total_minor, $order->currency, $idempotencyKey, $scenario));
            } catch (Throwable) {
                throw ValidationException::withMessages(['payment' => 'Test payment is not available.']);
            }

            $payment->update(['external_id' => $result->providerReference, 'provider_metadata' => $result->metadata]);
            if ($result->status === PaymentStatus::Succeeded) {
                return $this->complete->handle($payment);
            }

            $event = $result->status === PaymentStatus::Failed ? 'payment_failed' : 'payment_cancelled';
            $payment->update(['status' => $result->status, 'failure_code' => $result->failureCode,
                'failure_reason' => $result->status === PaymentStatus::Failed ? 'Payment was not completed.' : null,
                'completed_at' => now()]);
            $this->analytics->handle($event, $payment);

            return $payment->refresh();
        });
    }
}
