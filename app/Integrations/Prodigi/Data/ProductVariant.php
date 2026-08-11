<?php

namespace App\Integrations\Prodigi\Data;

final readonly class ProductVariant
{
    /** @param array<string, string> $attributes @param list<string> $shipsTo @param list<PrintArea> $printAreas */
    public function __construct(public array $attributes, public array $shipsTo, public array $printAreas) {}
}
