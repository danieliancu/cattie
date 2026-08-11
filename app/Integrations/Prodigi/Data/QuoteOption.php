<?php

namespace App\Integrations\Prodigi\Data;

final readonly class QuoteOption
{
    public function __construct(public string $shippingMethod, public Money $items, public Money $shipping, public Money $total, public ?string $carrierName, public ?string $carrierService, public ?string $fulfilmentCountry, public ?string $labCode) {}
}
