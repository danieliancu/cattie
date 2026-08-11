<?php

namespace App\Integrations\Prodigi\Data;

final readonly class QuoteResult
{
    /** @param array<string, string> $attributes @param list<QuoteOption> $options */
    public function __construct(public string $sku, public array $attributes, public int $quantity, public string $destinationCountry, public array $options) {}
}
