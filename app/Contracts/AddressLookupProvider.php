<?php

namespace App\Contracts;

use App\Data\AddressLookupResult;

interface AddressLookupProvider
{
    /**
     * @param  string  $canonicalPostcode  Canonically formatted UK postcode, e.g. "SS0 9XX".
     */
    public function lookup(string $canonicalPostcode): AddressLookupResult;
}
