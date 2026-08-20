<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\Payment;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompleteSuccessfulPayment
{
    public function __construct(private OrderPayability $payability, private TransitionOrder $transition, private RecordAnalyticsEvent $analytics) {}

    public function handle(Payment $payment): Payment
    {
        $justPaid = false;

        $payment = DB::transaction(function () use ($payment, &$justPaid) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Succeeded && $order->status === OrderStatus::Paid) {
                Cart::query()->where('converted_order_id', $order->id)->update(['status' => 'converted']);
                return $payment;
            }
            if ($payment->amount_minor !== $order->total_minor || $payment->currency !== $order->currency || ! $this->payability->check($order)) {
                throw ValidationException::withMessages(['payment' => 'This payment does not match a payable order.']);
            }

            $payment->update(['status' => PaymentStatus::Succeeded, 'failure_reason' => null, 'failure_code' => null, 'completed_at' => now()]);
            $this->transition->handle($order, OrderStatus::Paid, reason: 'Verified payment succeeded', metadata: ['payment_id' => $payment->id]);
            $order->update(['is_payable' => false]);
            Cart::query()->where('converted_order_id', $order->id)->update(['status' => 'converted']);
            $this->analytics->handle('payment_succeeded', $payment);
            $this->analytics->handle('order_paid', $order);
            $justPaid = true;

            return $payment->refresh();
        });

        // Send the confirmation once, only on the first successful transition, and
        // never let an email hiccup surface as a payment failure.
        if ($justPaid) {
            try {
                $order = $payment->order()->first();
                if ($order && $order->email) {
                    Mail::to($order->email)->queue(new OrderConfirmationMail($order));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $payment;
    }
}
