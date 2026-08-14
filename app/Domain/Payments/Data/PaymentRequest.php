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
        public ?string $paymentId = null,
        public ?string $orderNumber = null,
        public ?string $email = null,
        public array $lineItems = [],
        public ?string $returnUrl = null,
    ) {}
}
