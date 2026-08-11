<?php

namespace App\Domain\Payments\Contracts;

use App\Models\Order;

interface TaxResolver
{
    public function resolve(Order $order, int $shippingMinor): int;
}
