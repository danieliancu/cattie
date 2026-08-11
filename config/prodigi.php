<?php

return [
    'environment' => env('PRODIGI_ENV', 'sandbox'),
    'base_url' => env('PRODIGI_BASE_URL', 'https://api.sandbox.prodigi.com'),
    'api_key' => env('PRODIGI_API_KEY'),
    'timeout' => (int) env('PRODIGI_TIMEOUT', 30),
];
