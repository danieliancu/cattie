<?php

namespace App\Data;

final readonly class ImageGenerationReference
{
    public function __construct(
        public string $key,
        public string $role,
        public string $path,
        public string $mime,
        public string $sha256,
    ) {}
}
