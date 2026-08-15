<?php

namespace App\Data;

final readonly class AddressLookupResult
{
    /**
     * @param  list<AddressLookupAddress>  $addresses
     */
    public function __construct(
        public bool $valid,
        public ?string $postcode,
        public array $addresses = [],
        public array $metadata = [],
    ) {}
}
