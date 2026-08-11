<?php

namespace App\Providers\ImageGeneration;

use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationRequest;
use App\Data\ImageGenerationResult;
use App\Exceptions\ImageGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiImageGenerationProvider implements ImageGenerationProvider
{
    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        try {
            $response = Http::withToken(config('artwork.openai.api_key'))->timeout(config('artwork.openai.timeout'))->attach('image', file_get_contents($request->imagePath), basename($request->imagePath), ['Content-Type' => $request->imageMime])->post(config('artwork.openai.base_url').'/images/edits', ['model' => $request->model, 'prompt' => $request->prompt, 'quality' => $request->quality, 'size' => $request->size, 'n' => $request->candidates, 'output_format' => 'png']);
        } catch (ConnectionException $e) {
            throw new ImageGenerationException('Provider connection failed', 'transport', null, false);
        } if ($response->failed()) {
            $retryable = in_array($response->status(), [429, 500, 502, 503, 504], true);
            throw new ImageGenerationException('Provider rejected generation', 'provider', (string) $response->json('error.code'), $retryable);
        } $encoded = $response->json('data.0.b64_json');
        if (! is_string($encoded) || ($bytes = base64_decode($encoded, true)) === false) {
            throw new ImageGenerationException('Provider returned no image', 'invalid_response');
        }

        return new ImageGenerationResult($bytes, 'image/png', $response->header('x-request-id'), (array) $response->json('usage', []), ['created' => $response->json('created')]);
    }
}
