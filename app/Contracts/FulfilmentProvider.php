<?php

namespace App\Contracts;

use App\Models\Order;

interface FulfilmentProvider
{
    public function createOrder(Order $order, string $idempotencyKey): array;

    public function getOrder(string $externalId): array;

    public function cancelOrder(string $externalId, string $idempotencyKey): array;

    public function parseWebhook(string $payload, array $headers): array;
}
