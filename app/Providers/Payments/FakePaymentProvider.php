<?php

namespace App\Providers\Payments;

use App\Contracts\PaymentProvider;
use App\Domain\Payments\Data\PaymentRequest;
use App\Domain\Payments\Data\PaymentResult;
use App\Enums\PaymentStatus;
use Illuminate\Support\Str;
use RuntimeException;

class FakePaymentProvider implements PaymentProvider
{
    public function create(PaymentRequest $request): PaymentResult
    {
        if (! config('payments.fake.enabled') || ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fake payments are disabled.');
        }

        $status = match ($request->scenario) {
            'success' => PaymentStatus::Succeeded,
            'failure' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            default => throw new RuntimeException('Unsupported fake payment outcome.'),
        };

        return new PaymentResult(
            'fake_pay_'.Str::lower(Str::random(24)),
            $status,
            $status === PaymentStatus::Failed ? 'simulated_decline' : null,
            ['simulated' => true],
        );
    }

    public function refund(string $externalId, int $amountMinor, string $idempotencyKey): array
    {
        throw new RuntimeException('Refunds are outside Phase 5.');
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        throw new RuntimeException('Fake payments do not use HTTP webhooks.');
    }
}
