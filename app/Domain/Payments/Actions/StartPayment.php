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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function handle(Order $order, string $idempotencyKey, string $scenario = 'success'): Payment
    {
        [$payment, $request, $alreadyStarted] = DB::transaction(function () use ($order, $idempotencyKey, $scenario) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless($existing->order_id === $order->id, 409);

                return [$existing, null, $existing->external_id !== null || $existing->status !== PaymentStatus::Pending];
            }

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->status !== OrderStatus::AwaitingPayment || ! $this->payability->check($order)) {
                throw ValidationException::withMessages(['payment' => 'This order is not ready for payment.']);
            }

            $payment = $order->payments()->create([
                'provider' => config('payments.provider'), 'idempotency_key' => $idempotencyKey,
                'status' => PaymentStatus::Pending, 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
            ]);
            $this->analytics->handle('payment_started', $payment);

            return [$payment, $this->request($order, $payment, $idempotencyKey, $scenario), false];
        });

        if ($alreadyStarted) {
            return $payment->refresh();
        }

        if ($request === null) {
            $order = $payment->order()->with('items')->firstOrFail();
            $request = $this->request($order, $payment, $idempotencyKey, $scenario);
        }

        try {
            $result = $this->provider->create($request);
        } catch (Throwable $exception) {
            Log::warning('Payment provider could not start payment.', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'provider' => $payment->provider,
                'exception' => $exception::class,
                'provider_code' => method_exists($exception, 'getStripeCode') ? $exception->getStripeCode() : null,
            ]);
            Payment::query()->whereKey($payment->id)->where('status', PaymentStatus::Pending->value)->whereNull('external_id')->delete();
            throw ValidationException::withMessages(['payment' => "We couldn't start your payment. Please try again."]);
        }

        $payment->update([
            'external_id' => $result->providerReference,
            'provider_metadata' => $result->metadata,
        ]);
        if ($result->status === PaymentStatus::Succeeded) {
            return $this->complete->handle($payment);
        }
        if ($result->status === PaymentStatus::Pending) {
            return $payment->refresh();
        }

        $payment->update([
            'status' => $result->status, 'failure_code' => $result->failureCode,
            'failure_reason' => $result->status === PaymentStatus::Failed ? 'Payment was not completed.' : null,
            'completed_at' => now(),
        ]);
        $this->analytics->handle($result->status === PaymentStatus::Failed ? 'payment_failed' : 'payment_cancelled', $payment);

        return $payment->refresh();
    }

    private function request(Order $order, Payment $payment, string $idempotencyKey, string $scenario): PaymentRequest
    {
        if ((int) $order->discount_minor !== 0) {
            throw ValidationException::withMessages(['payment' => 'This discounted order cannot be paid through Stripe yet.']);
        }

        $items = [];
        $calculated = 0;
        foreach ($order->items as $item) {
            if ($item->currency !== $order->currency || $item->total_price_minor !== $item->unit_price_minor * $item->quantity) {
                throw ValidationException::withMessages(['payment' => 'The order items do not match the final total.']);
            }
            $description = collect([
                $item->variant_name,
                ...collect($item->personalisation ?? [])->map(function (array $field) {
                    $value = trim((string) ($field['value'] ?? ''));
                    if ($value === '') return null;
                    $label = trim((string) ($field['label'] ?? $field['key'] ?? 'Personalisation'));

                    return $label.': '.$value;
                })->filter()->all(),
            ])->filter()->implode(' · ');
            $items[] = [
                'name' => Str::limit($item->product_name, 250, ''),
                'description' => Str::limit($description, 500, ''),
                'unit_amount' => $item->unit_price_minor,
                'quantity' => $item->quantity,
                'currency' => $order->currency,
            ];
            $calculated += $item->unit_price_minor * $item->quantity;
        }
        foreach ([[data_get($order->shipping_method_snapshot, 'name', 'UK delivery'), $order->shipping_minor], ['Tax', $order->tax_minor]] as [$name, $amount]) {
            if ((int) $amount > 0) {
                $items[] = ['name' => $name, 'description' => '', 'unit_amount' => (int) $amount, 'quantity' => 1, 'currency' => $order->currency];
                $calculated += (int) $amount;
            }
        }
        if ($calculated !== $order->total_minor) {
            throw ValidationException::withMessages(['payment' => 'The payment total does not match the order total.']);
        }

        return new PaymentRequest(
            $order->id, $order->total_minor, $order->currency, $idempotencyKey, $scenario,
            $payment->id, $order->number, $order->email, $items,
            route('checkout.stripe-return', ['orderNumber' => $order->number]).'?session_id={CHECKOUT_SESSION_ID}',
        );
    }
}
