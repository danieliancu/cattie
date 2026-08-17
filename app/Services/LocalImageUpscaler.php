<?php

namespace App\Services;

use App\Contracts\ImageUpscaler;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Shells out to a Python upscaler (Real-ESRGAN when a model is configured, otherwise a
 * high-quality Pillow LANCZOS resample), mirroring the background-removal runner. The
 * caller (CharacterUpscaleProcessor) falls back to an in-process GD upscale if this is
 * unavailable, so production can plug in a real super-resolution model without the
 * storefront depending on it.
 */
final class LocalImageUpscaler implements ImageUpscaler
{
    public function upscale(string $inputPath, string $outputPath, int $targetLongEdge): void
    {
        try {
            $process = new Process([
                (string) config('artwork.upscaler.python'),
                base_path('scripts/upscale_artwork.py'),
                '--input', $inputPath,
                '--output', $outputPath,
                '--target', (string) $targetLongEdge,
                '--model', (string) config('artwork.upscaler.model'),
            ]);
            $process->setTimeout((float) config('artwork.upscaler.timeout'));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new RuntimeException('Local upscaler did not produce an output.');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Local upscaler unavailable: '.$e->getMessage(), 0, $e);
        }
    }
}
