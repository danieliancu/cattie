<?php

namespace App\Contracts;

use App\Domain\Payments\Data\PaymentRequest;
use App\Domain\Payments\Data\PaymentResult;

interface PaymentProvider
{
    public function create(PaymentRequest $request): PaymentResult;

    public function retrieve(string $providerReference): PaymentResult;

    public function refund(string $externalId, int $amountMinor, string $idempotencyKey): array;

    public function cancel(string $externalId): void;

    public function parseWebhook(string $payload, array $headers): array;
}
