<?php

return [
    'provider' => env('AI_IMAGE_PROVIDER', 'fake'), 'model' => env('AI_IMAGE_MODEL', 'gpt-image-2'), 'quality' => env('AI_IMAGE_QUALITY', 'medium'), 'size' => env('AI_IMAGE_SIZE', '1024x1536'), 'candidates' => 1,
    'max_generations_per_session' => (int) env('ARTWORK_MAX_GENERATIONS', 3), 'poll_interval_ms' => (int) env('ARTWORK_POLL_INTERVAL_MS', 3000), 'retention_days' => (int) env('ARTWORK_RETENTION_DAYS', 30),
    'fake_failure' => (bool) env('AI_IMAGE_FAKE_FAILURE', false),
    'upload' => ['max_kb' => (int) env('ARTWORK_UPLOAD_MAX_KB', 10240), 'min_dimension' => 512, 'max_dimension' => 8000, 'normalised_max_dimension' => 2048],
    'openai' => ['api_key' => env('OPENAI_API_KEY'), 'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), 'timeout' => (int) env('AI_IMAGE_TIMEOUT', 180)],
];
