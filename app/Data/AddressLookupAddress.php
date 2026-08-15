<?php

namespace App\Data;

final readonly class AddressLookupAddress
{
    public function __construct(
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $county,
        public string $postcode,
        public string $country = 'GB',
    ) {}
}
