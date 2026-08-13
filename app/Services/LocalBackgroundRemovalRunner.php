<?php

namespace App\Services;

use App\Contracts\BackgroundRemovalRunner;
use App\Exceptions\ImageGenerationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class LocalBackgroundRemovalRunner implements BackgroundRemovalRunner
{
    public function run(string $inputPath, string $outputPath): void
    {
        try {
            $process = new Process([
                (string) config('artwork.background_removal.python'),
                base_path('scripts/remove_artwork_background.py'),
                '--input', $inputPath,
                '--output', $outputPath,
                '--model', (string) config('artwork.background_removal.model'),
            ]);
            $process->setTimeout((float) config('artwork.background_removal.timeout'));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new ImageGenerationException('Local background removal failed.', 'post_processing', 'background_removal_failed', false);
            }
        } catch (ProcessTimedOutException $e) {
            throw new ImageGenerationException('Local background removal timed out.', 'post_processing', 'background_removal_timeout', true);
        } catch (ImageGenerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ImageGenerationException('Local background removal is not configured correctly.', 'configuration', 'background_removal_unavailable', false);
        }
    }
}
