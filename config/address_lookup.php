<?php

return [
    'provider' => env('ADDRESS_LOOKUP_PROVIDER', 'postcodes_io'),

    'postcodes_io' => [
        'base_url' => env('POSTCODES_IO_BASE_URL', 'https://api.postcodes.io'),
        'timeout' => (int) env('POSTCODES_IO_TIMEOUT', 5),
        'connect_timeout' => (int) env('POSTCODES_IO_CONNECT_TIMEOUT', 3),
    ],

    // Property-level provider. Free tier: 100 calls/month, no card required.
    // Sign up at https://homedata.co.uk/register to get an API key.
    'homedata' => [
        'base_url' => env('HOMEDATA_BASE_URL', 'https://api.homedata.co.uk'),
        'api_key' => env('HOMEDATA_API_KEY'),
        'timeout' => (int) env('HOMEDATA_TIMEOUT', 5),
        'connect_timeout' => (int) env('HOMEDATA_CONNECT_TIMEOUT', 3),
    ],

    // Property-level provider. Requires a billing-enabled Google Cloud project
    // (Places API (New) enabled) even though usage typically stays within the
    // monthly free credit at Cattie's volume.
    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
        'timeout' => (int) env('GOOGLE_PLACES_TIMEOUT', 5),
        'connect_timeout' => (int) env('GOOGLE_PLACES_CONNECT_TIMEOUT', 3),
    ],
];
