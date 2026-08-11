<?php

namespace App\Domain\Payments\Resolvers;

use App\Domain\Payments\Contracts\TaxResolver;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class ZeroUkTaxResolver implements TaxResolver
{
    public function resolve(Order $order, int $shippingMinor): int
    {
        if (($order->shipping_address['country'] ?? null) !== 'GB') {
            throw ValidationException::withMessages(['tax' => 'The provisional UK tax strategy cannot resolve this address.']);
        }

        return 0;
    }
}
