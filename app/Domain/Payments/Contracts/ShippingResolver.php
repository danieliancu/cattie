<?php

namespace App\Domain\Payments\Contracts;

use App\Models\Order;

interface ShippingResolver
{
    public function resolve(Order $order): int;
}
