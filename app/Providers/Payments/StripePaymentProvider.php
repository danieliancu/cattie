<?php

namespace App\Providers\Payments;

use App\Contracts\PaymentProvider;
use App\Domain\Payments\Data\PaymentRequest;
use App\Domain\Payments\Data\PaymentResult;
use App\Enums\PaymentStatus;
use App\Integrations\Stripe\StripeGateway;
use RuntimeException;

final class StripePaymentProvider implements PaymentProvider
{
    public function __construct(private StripeGateway $gateway) {}

    public function create(PaymentRequest $request): PaymentResult
    {
        if (! $request->paymentId || ! $request->orderNumber || ! $request->email || ! $request->returnUrl || $request->lineItems === []) {
            throw new RuntimeException('Stripe Checkout received incomplete payment data.');
        }

        $lineItems = array_map(fn (array $item) => [
            'price_data' => [
                'currency' => strtolower($item['currency']),
                'unit_amount' => $item['unit_amount'],
                'product_data' => array_filter(['name' => $item['name'], 'description' => $item['description'] ?? null]),
            ],
            'quantity' => $item['quantity'],
        ], $request->lineItems);

        $metadata = [
            'cattie_order_id' => $request->orderId,
            'cattie_order_number' => $request->orderNumber,
            'cattie_payment_id' => $request->paymentId,
        ];
        $session = $this->gateway->createCheckoutSession([
            'mode' => 'payment',
            'ui_mode' => 'embedded',
            'redirect_on_completion' => 'if_required',
            'customer_email' => $request->email,
            'client_reference_id' => $request->orderNumber,
            'line_items' => $lineItems,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'return_url' => $request->returnUrl,
        ], $request->idempotencyKey);

        if (! isset($session['id'], $session['client_secret'])) {
            throw new RuntimeException('Stripe did not return an Embedded Checkout client secret.');
        }

        return new PaymentResult(
            $session['id'], PaymentStatus::Pending, metadata: $this->safeMetadata($session),
            amountMinor: $session['amount_total'] ?? null,
            currency: isset($session['currency']) ? strtoupper($session['currency']) : null,
            clientSecret: $session['client_secret'],
        );
    }

    public function retrieve(string $providerReference): PaymentResult
    {
        return $this->resultFromSession($this->gateway->retrieveCheckoutSession($providerReference));
    }

    public function refund(string $externalId, int $amountMinor, string $idempotencyKey): array
    {
        $session = $this->gateway->retrieveCheckoutSession($externalId);
        $paymentIntent = $session['payment_intent'] ?? null;
        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }
        if (! is_string($paymentIntent) || $paymentIntent === '') {
            throw new RuntimeException('Stripe Checkout Session has no refundable PaymentIntent.');
        }

        return $this->gateway->createRefund(['payment_intent' => $paymentIntent, 'amount' => $amountMinor], $idempotencyKey);
    }

    public function cancel(string $externalId): void
    {
        $session = $this->gateway->expireCheckoutSession($externalId);
        if (($session['status'] ?? null) !== 'expired') {
            throw new RuntimeException('Stripe Checkout Session could not be expired.');
        }
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        $secret = config('payments.stripe.webhook_secret');
        $signature = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;
        if (! is_string($secret) || $secret === '' || ! is_string($signature) || $signature === '') {
            throw new RuntimeException('Stripe webhook authentication is not configured.');
        }

        $event = $this->gateway->constructWebhookEvent($payload, $signature, $secret);
        $session = $event['data']['object'] ?? [];
        $result = $this->resultFromSession($session, $event['type'] ?? null);

        return ['event_id' => $event['id'] ?? null, 'event_type' => $event['type'] ?? null, 'result' => $result];
    }

    private function resultFromSession(array $session, ?string $eventType = null): PaymentResult
    {
        if (! isset($session['id'])) {
            throw new RuntimeException('Stripe Checkout Session is invalid.');
        }
        $status = match (true) {
            $eventType === 'checkout.session.async_payment_failed' => PaymentStatus::Failed,
            $eventType === 'checkout.session.expired', ($session['status'] ?? null) === 'expired' => PaymentStatus::Cancelled,
            ($session['payment_status'] ?? null) === 'paid' => PaymentStatus::Succeeded,
            default => PaymentStatus::Pending,
        };

        return new PaymentResult(
            $session['id'], $status,
            failureCode: $status === PaymentStatus::Failed ? 'async_payment_failed' : ($status === PaymentStatus::Cancelled ? 'checkout_session_expired' : null),
            metadata: $this->safeMetadata($session),
            amountMinor: isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            currency: isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
            clientSecret: $session['client_secret'] ?? null,
        );
    }

    private function safeMetadata(array $session): array
    {
        $paymentIntent = $session['payment_intent'] ?? null;
        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        return array_filter([
            'payment_intent_id' => $paymentIntent,
            'payment_status' => $session['payment_status'] ?? null,
            'session_status' => $session['status'] ?? null,
            'client_reference_id' => $session['client_reference_id'] ?? null,
            'cattie_order_id' => $session['metadata']['cattie_order_id'] ?? null,
            'cattie_order_number' => $session['metadata']['cattie_order_number'] ?? null,
            'cattie_payment_id' => $session['metadata']['cattie_payment_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
