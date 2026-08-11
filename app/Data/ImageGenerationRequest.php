<?php

namespace App\Data;

final readonly class ImageGenerationRequest
{
    public function __construct(
        public string $generationId,
        public string $prompt,
        public string $imagePath,
        public string $imageMime,
        public string $model,
        public string $quality,
        public string $size,
        public int $candidates = 1,
        public int $generationSequence = 1,
    ) {}
}
