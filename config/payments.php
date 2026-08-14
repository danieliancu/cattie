<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'fake'),
    'fake' => ['enabled' => (bool) env('FAKE_PAYMENTS_ENABLED', false)],
    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'tax' => ['strategy' => env('CHECKOUT_TAX_STRATEGY', 'zero_uk')],
];
