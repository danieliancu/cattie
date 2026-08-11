<?php

namespace App\Domain\Payments\Data;

final readonly class PaymentRequest
{
    public function __construct(
        public string $orderId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $scenario = 'success',
    ) {}
}
