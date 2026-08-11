<?php

namespace App\Services;

final class AiGenerationCostCalculator
{
    public function calculate(string $model, string $quality, string $size, array $usage): array
    {
        if (isset($usage['total_cost_micros']) && is_int($usage['total_cost_micros'])) {
            return ['micros' => $usage['total_cost_micros'], 'currency' => $usage['cost_currency'] ?? config('ai-pricing.currency', 'USD'), 'basis' => 'actual', 'pricing_version' => config('ai-pricing.version')];
        }

        $estimate = config("ai-pricing.estimates.$model.$quality.$size");

        return ['micros' => $estimate, 'currency' => config('ai-pricing.currency', 'USD'), 'basis' => $estimate ? 'estimated' : 'unavailable', 'pricing_version' => config('ai-pricing.version')];
    }
}
