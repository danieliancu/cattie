<?php

namespace App\Integrations\Stripe;

use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

final class SdkStripeGateway implements StripeGateway
{
    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->checkout->sessions->create($parameters, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, [])->toArray();
    }

    public function expireCheckoutSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->expire($sessionId, [])->toArray();
    }

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array
    {
        return Webhook::constructEvent($payload, $signature, $secret)->toArray();
    }

    public function createRefund(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->refunds->create($parameters, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    private function client(): StripeClient
    {
        $key = config('payments.stripe.secret_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($key);
    }
}
