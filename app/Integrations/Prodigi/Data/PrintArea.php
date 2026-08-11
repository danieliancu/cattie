<?php

namespace App\Integrations\Prodigi\Data;

final readonly class PrintArea
{
    public function __construct(public string $name, public bool $required, public ?int $horizontalResolution = null, public ?int $verticalResolution = null) {}
}
