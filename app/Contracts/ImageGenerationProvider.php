<?php

namespace App\Contracts;

use App\Data\ImageGenerationRequest;
use App\Data\ImageGenerationResult;

interface ImageGenerationProvider
{
    public function generate(ImageGenerationRequest $request): ImageGenerationResult;
}
