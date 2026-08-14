<?php

namespace App\Integrations\Stripe;

interface StripeGateway
{
    public function createCheckoutSession(array $parameters, string $idempotencyKey): array;

    public function retrieveCheckoutSession(string $sessionId): array;

    public function expireCheckoutSession(string $sessionId): array;

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array;

    public function createRefund(array $parameters, string $idempotencyKey): array;
}
