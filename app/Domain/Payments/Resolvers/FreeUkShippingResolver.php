<?php

namespace App\Domain\Payments\Resolvers;

use App\Domain\Payments\Contracts\ShippingResolver;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class FreeUkShippingResolver implements ShippingResolver
{
    public function resolve(Order $order): int
    {
        if (($order->shipping_address['country'] ?? null) !== 'GB') {
            throw ValidationException::withMessages(['shipping' => 'Free UK delivery is only available for UK addresses.']);
        }

        return 0;
    }
}
