<?php

namespace App\Integrations\Prodigi\Data;

final readonly class ProductDetails
{
    /** @param array<string, list<string>> $attributes @param list<PrintArea> $printAreas @param list<ProductVariant> $variants */
    public function __construct(public string $sku, public string $description, public ?float $width, public ?float $height, public ?string $dimensionUnits, public array $attributes, public array $printAreas, public array $variants) {}
}
