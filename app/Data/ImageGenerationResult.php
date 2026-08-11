<?php

namespace App\Data;

final readonly class ImageGenerationResult
{
    public function __construct(public string $contents, public string $mimeType = 'image/png', public ?string $requestId = null, public array $usage = [], public array $metadata = []) {}
}
