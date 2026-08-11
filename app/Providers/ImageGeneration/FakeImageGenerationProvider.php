<?php

namespace App\Providers\ImageGeneration;

use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationRequest;
use App\Data\ImageGenerationResult;
use App\Exceptions\ImageGenerationException;

class FakeImageGenerationProvider implements ImageGenerationProvider
{
    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        if (config('artwork.fake_failure')) {
            throw new ImageGenerationException('Simulated failure', 'simulated', 'fake_failure', false);
        }

        $sequence = max(1, min(3, $request->generationSequence));
        $variant = ['a', 'b', 'c'][$sequence - 1];
        $path = database_path("seeders/assets/fake-artwork/fake-artwork-{$variant}.png");

        if (! is_file($path)) {
            throw new ImageGenerationException('Fake artwork asset is unavailable', 'configuration', 'fake_asset_missing', false);
        }

        return new ImageGenerationResult(
            file_get_contents($path),
            'image/png',
            'fake-'.$request->generationId,
            ['input_image_tokens' => 100, 'output_image_tokens' => 1000],
            ['fake' => true, 'variant' => $variant, 'generation_sequence' => $sequence],
        );
    }
}
