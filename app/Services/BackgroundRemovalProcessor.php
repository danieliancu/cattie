<?php

namespace App\Services;

use App\Contracts\BackgroundRemovalRunner;
use App\Exceptions\ImageGenerationException;

final class BackgroundRemovalProcessor
{
    public function __construct(private ?BackgroundRemovalRunner $runner = null) {}

    /** @return array{contents:string,mime_type:string,width:int,height:int,processed:bool} */
    public function process(string $contents, array $requirements): array
    {
        $info = @getimagesizefromstring($contents);
        if ($info === false) {
            throw new ImageGenerationException('Generated artwork is not a valid image.', 'post_processing', 'invalid_source_image', false);
        }

        if (! ($requirements['transparent_background'] ?? false)) {
            return ['contents' => $contents, 'mime_type' => $info['mime'], 'width' => $info[0], 'height' => $info[1], 'processed' => false];
        }

        if ($this->hasMeaningfulAlpha($contents)) {
            return ['contents' => $contents, 'mime_type' => 'image/png', 'width' => $info[0], 'height' => $info[1], 'processed' => false];
        }

        $directory = storage_path('app/private/artwork/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new ImageGenerationException('Artwork processing storage is unavailable.', 'configuration', 'temporary_storage_unavailable', false);
        }

        $token = bin2hex(random_bytes(20));
        $input = $directory.'/'.$token.'-input.png';
        $output = $directory.'/'.$token.'-output.png';
        file_put_contents($input, $contents, LOCK_EX);

        try {
            ($this->runner ?? app(BackgroundRemovalRunner::class))->run($input, $output);

            $processed = file_get_contents($output);
            $processedInfo = $processed === false ? false : @getimagesizefromstring($processed);
            if ($processedInfo === false || ($processedInfo['mime'] ?? null) !== 'image/png' || ! $this->hasMeaningfulAlpha($processed)) {
                throw new ImageGenerationException('Local background removal produced an invalid image.', 'post_processing', 'invalid_transparent_output', false);
            }

            return ['contents' => $processed, 'mime_type' => 'image/png', 'width' => $processedInfo[0], 'height' => $processedInfo[1], 'processed' => true];
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function hasMeaningfulAlpha(string $contents): bool
    {
        $image = @imagecreatefromstring($contents);
        if (! $image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(max($width, $height) / 400));
        $transparent = 0;
        $subject = 0;
        $samples = 0;
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                $transparent += $alpha >= 120 ? 1 : 0;
                $subject += $alpha <= 100 ? 1 : 0;
                $samples++;
            }
        }
        imagedestroy($image);

        return $samples > 0 && $transparent / $samples >= .01 && $subject / $samples >= .01;
    }
}
