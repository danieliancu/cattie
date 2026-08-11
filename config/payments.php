<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'fake'),
    'fake' => ['enabled' => (bool) env('FAKE_PAYMENTS_ENABLED', false)],
    'shipping' => ['strategy' => env('CHECKOUT_SHIPPING_STRATEGY', 'free_uk')],
    'tax' => ['strategy' => env('CHECKOUT_TAX_STRATEGY', 'zero_uk')],
];
