<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Payments\Data\PaymentResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReconcilePayment
{
    public function __construct(private CompleteSuccessfulPayment $complete, private RecordAnalyticsEvent $analytics) {}

    public function handle(PaymentResult $result, ?Payment $expectedPayment = null): Payment
    {
        $changedToFailure = false;
        $payment = DB::transaction(function () use ($result, $expectedPayment, &$changedToFailure) {
            $payment = Payment::query()->lockForUpdate()->where('provider', 'stripe')->where('external_id', $result->providerReference)->first();
            if (! $payment || ($expectedPayment && ! $payment->is($expectedPayment))) {
                throw ValidationException::withMessages(['payment' => 'The Stripe payment could not be reconciled.']);
            }
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $metadata = $result->metadata;
            $matches = ($metadata['cattie_payment_id'] ?? null) === $payment->id
                && ($metadata['cattie_order_id'] ?? null) === $order->id
                && ($metadata['cattie_order_number'] ?? null) === $order->number
                && ($metadata['client_reference_id'] ?? $order->number) === $order->number
                && $result->amountMinor === $payment->amount_minor
                && strtoupper((string) $result->currency) === strtoupper($payment->currency);
            if (! $matches) {
                throw ValidationException::withMessages(['payment' => 'The Stripe payment details do not match the order.']);
            }

            $payment->update(['provider_metadata' => [...($payment->provider_metadata ?? []), ...$metadata]]);
            if ($payment->status === PaymentStatus::Succeeded && $order->status === OrderStatus::Paid) {
                return $payment;
            }
            if ($result->status === PaymentStatus::Failed || $result->status === PaymentStatus::Cancelled) {
                $changedToFailure = $payment->status !== $result->status;
                $payment->update([
                    'status' => $result->status, 'failure_code' => $result->failureCode,
                    'failure_reason' => $result->status === PaymentStatus::Failed ? 'Payment was not completed.' : null,
                    'completed_at' => now(),
                ]);
            }

            return $payment->refresh();
        });

        if ($changedToFailure) {
            $this->analytics->handle($result->status === PaymentStatus::Failed ? 'payment_failed' : 'payment_cancelled', $payment);
        }

        return $result->status === PaymentStatus::Succeeded ? $this->complete->handle($payment) : $payment;
    }
}
