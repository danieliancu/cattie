<?php

namespace App\Domain\Payments\Resolvers;

use App\Domain\Payments\Contracts\ShippingResolver;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class OrderShippingMethodResolver implements ShippingResolver
{
    public function resolve(Order $order): int
    {
        $snapshot = $order->shipping_method_snapshot;
        if (! is_array($snapshot)
            || ($snapshot['shipping_method_id'] ?? null) !== $order->shipping_method_id
            || ($snapshot['country'] ?? null) !== ($order->shipping_address['country'] ?? null)
            || ($snapshot['currency'] ?? null) !== $order->currency
            || ! isset($snapshot['provider'], $snapshot['provider_service_code'], $snapshot['name'], $snapshot['price_minor'])
            || ! is_int($snapshot['price_minor'])
            || $snapshot['price_minor'] < 0) {
            throw ValidationException::withMessages(['shipping' => 'The selected delivery method is no longer valid.']);
        }

        return $snapshot['price_minor'];
    }
}
