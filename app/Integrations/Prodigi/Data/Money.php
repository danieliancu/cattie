<?php

namespace App\Integrations\Prodigi\Data;

final readonly class Money
{
    public function __construct(public string $amount, public string $currency) {}
}
