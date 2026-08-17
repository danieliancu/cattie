<?php

namespace App\Contracts;

interface ImageUpscaler
{
    /**
     * Upscale the image at $inputPath so its long edge is roughly $targetLongEdge px,
     * preserving the alpha channel, and write the result to $outputPath.
     */
    public function upscale(string $inputPath, string $outputPath, int $targetLongEdge): void;
}
