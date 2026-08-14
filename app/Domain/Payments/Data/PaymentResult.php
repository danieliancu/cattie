<?php

namespace App\Domain\Payments\Data;

use App\Enums\PaymentStatus;

final readonly class PaymentResult
{
    public function __construct(
        public string $providerReference,
        public PaymentStatus $status,
        public ?string $failureCode = null,
        public array $metadata = [],
        public ?string $redirectUrl = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?string $clientSecret = null,
    ) {}
}
