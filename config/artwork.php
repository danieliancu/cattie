<?php

return [
    'provider' => env('AI_IMAGE_PROVIDER', 'fake'), 'model' => env('AI_IMAGE_MODEL', 'gpt-image-2'), 'quality' => env('AI_IMAGE_QUALITY', 'medium'), 'size' => env('AI_IMAGE_SIZE', '1024x1536'), 'candidates' => 1,
    'poll_interval_ms' => (int) env('ARTWORK_POLL_INTERVAL_MS', 3000), 'retention_days' => (int) env('ARTWORK_RETENTION_DAYS', 30),
    'fake_failure' => (bool) env('AI_IMAGE_FAKE_FAILURE', false),
    'upload' => ['max_kb' => (int) env('ARTWORK_UPLOAD_MAX_KB', 10240), 'min_dimension' => 512, 'max_dimension' => 8000, 'normalised_max_dimension' => 2048],
    'openai' => ['api_key' => env('OPENAI_API_KEY'), 'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), 'connect_timeout' => (int) env('AI_IMAGE_CONNECT_TIMEOUT', 10), 'timeout' => (int) env('AI_IMAGE_TIMEOUT', 180)],
    'background_removal' => ['python' => env('AI_BACKGROUND_REMOVAL_PYTHON', 'python'), 'model' => 'isnet-general-use', 'timeout' => 180],
    'style_references' => [
        'storybook-cartoon-v3' => [
            'path' => base_path('resources/ai/style-references/storybook-cartoon-v3.png'),
            'mime' => 'image/png',
            'role' => 'style_only',
            'sha256' => '6d265db583ff093f3378bd621462c8a5e66819b090965d3f857dfa6aa401ce28',
        ],
        'storybook-cartoon-v4' => [
            'path' => base_path('resources/ai/style-references/storybook-cartoon-v4.png'),
            'mime' => 'image/png',
            'role' => 'style_only',
            'sha256' => '0c9c129b9389a81fe1adfbba03ca150997e168f3cf44236de61d9870c4c7d53d',
        ],
        'hand-drawn-v3' => [
            'path' => base_path('resources/ai/style-references/hand-drawn-v3.png'),
            'mime' => 'image/png',
            'role' => 'style_only',
            'sha256' => '1155ad10792a0efaa2bcb8489789525ab773fbe14876f580585b98bd6eb60dc7',
        ],
        'hand-drawn-v4' => [
            'path' => base_path('resources/ai/style-references/hand-drawn-v4.png'),
            'mime' => 'image/png',
            'role' => 'style_only',
            'sha256' => '0dc842a65929412dcd7d6ef9c32b676442a3142be1d4e2ff17a36484c3cf4568',
        ],
    ],
];
