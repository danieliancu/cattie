<?php

namespace App\Providers\ImageGeneration;

use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationRequest;
use App\Data\ImageGenerationResult;
use App\Exceptions\ImageGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiImageGenerationProvider implements ImageGenerationProvider
{
    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        $apiKey = trim((string) config('artwork.openai.api_key'));
        if ($apiKey === '') {
            throw new ImageGenerationException('OpenAI image generation is not configured.', 'configuration', 'missing_api_key', false);
        }

        $image = @file_get_contents($request->imagePath);
        if ($image === false || @getimagesizefromstring($image) === false) {
            throw new ImageGenerationException('The provider input image is unavailable.', 'input', 'invalid_input_image', false);
        }

        try {
            $pending = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(config('artwork.openai.connect_timeout'))
                ->timeout(config('artwork.openai.timeout'))
                ->attach('image[]', $image, 'content.'.($request->imageMime === 'image/png' ? 'png' : 'webp'), ['Content-Type' => $request->imageMime]);

            foreach ($request->references as $index => $reference) {
                $referenceBytes = @file_get_contents($reference->path);
                $referenceInfo = $referenceBytes === false ? false : @getimagesizefromstring($referenceBytes);
                if ($referenceInfo === false
                    || ($referenceInfo['mime'] ?? null) !== $reference->mime
                    || ! hash_equals($reference->sha256, hash('sha256', $referenceBytes))) {
                    throw new ImageGenerationException('A provider style reference is unavailable.', 'input', 'invalid_reference_image', false);
                }
                $extension = $reference->mime === 'image/png' ? 'png' : 'webp';
                $pending->attach('image[]', $referenceBytes, 'reference-'.($index + 1).'.'.$extension, ['Content-Type' => $reference->mime]);
            }

            $response = $pending->post(rtrim(config('artwork.openai.base_url'), '/').'/images/edits', [
                'model' => $request->model,
                'prompt' => $request->prompt,
                'quality' => $request->quality,
                'size' => $request->size,
                'n' => $request->candidates,
                'output_format' => 'png',
            ]);
        } catch (ImageGenerationException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            throw new ImageGenerationException('OpenAI image generation could not be reached.', 'transport', 'connection_failed', true);
        } catch (Throwable $e) {
            throw new ImageGenerationException('OpenAI image generation could not be requested.', 'transport', 'request_failed', true);
        }

        if ($response->failed()) {
            $status = $response->status();
            $code = $response->json('error.code');
            $retryable = $status === 429 || $status >= 500;
            $category = match (true) {
                in_array($status, [401, 403], true) => 'authentication',
                $status === 429 => 'rate_limit',
                $status >= 500 => 'provider_unavailable',
                default => 'invalid_request',
            };

            throw new ImageGenerationException('OpenAI rejected the image generation request.', $category, is_scalar($code) ? (string) $code : 'http_'.$status, $retryable);
        }

        $encoded = $response->json('data.0.b64_json');
        if (! is_string($encoded) || ($bytes = base64_decode($encoded, true)) === false) {
            throw new ImageGenerationException('OpenAI returned no usable image.', 'invalid_response', 'missing_image', false);
        }
        $imageInfo = @getimagesizefromstring($bytes);
        if ($imageInfo === false || ($imageInfo['mime'] ?? null) !== 'image/png') {
            throw new ImageGenerationException('OpenAI returned malformed image data.', 'invalid_response', 'malformed_image', false);
        }

        return new ImageGenerationResult($bytes, 'image/png', $response->header('x-request-id'), (array) $response->json('usage', []), [
            'created' => $response->json('created'),
            'model' => $request->model,
            'quality' => $request->quality,
            'size' => $request->size,
            'output_format' => 'png',
        ]);
    }
}
