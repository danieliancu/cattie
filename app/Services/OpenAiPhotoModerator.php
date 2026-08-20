<?php

namespace App\Services;

use App\Contracts\PhotoModerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OpenAiPhotoModerator implements PhotoModerator
{
    private const SUBJECT_INSTRUCTION = 'You classify a customer photo for a personalised-gift service. '
        .'Reply ONLY with compact JSON {"person":boolean,"pet":boolean}. '
        .'person=true when a human is clearly visible. '
        .'pet=true when a pet animal such as a dog, cat, rabbit, hamster, guinea pig or ferret is clearly visible. '
        .'Birds do NOT count as pets: if the only animal is a bird, pet=false. '
        .'Objects, scenery, text or empty photos give person=false and pet=false.';

    public function moderate(string $absolutePath, string $mimeType): PhotoModerationResult
    {
        $apiKey = trim((string) config('artwork.openai.api_key'));
        if ($apiKey === '' || ! is_file($absolutePath)) {
            // Fail open: never block a customer because moderation is misconfigured.
            return PhotoModerationResult::allowed();
        }

        try {
            $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($absolutePath));
            $base = rtrim((string) config('artwork.openai.base_url'), '/');
            $timeout = (int) config('artwork.moderation.timeout');

            // 1) Safety / legal.
            $moderation = Http::withToken($apiKey)->acceptJson()->timeout($timeout)->post($base.'/moderations', [
                'model' => config('artwork.moderation.moderation_model'),
                'input' => [['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]],
            ]);
            if ($moderation->successful() && data_get($moderation->json(), 'results.0.flagged') === true) {
                return PhotoModerationResult::rejected(PhotoModerationResult::UNSAFE);
            }

            // 2) Subject: a person or a (non-bird) pet.
            $vision = Http::withToken($apiKey)->acceptJson()->timeout($timeout)->post($base.'/chat/completions', [
                'model' => config('artwork.moderation.vision_model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SUBJECT_INSTRUCTION],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'Classify this photo.'],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ]],
                ],
            ]);
            if ($vision->successful()) {
                $parsed = json_decode((string) data_get($vision->json(), 'choices.0.message.content', ''), true);
                if (is_array($parsed) && ! ((bool) ($parsed['person'] ?? false) || (bool) ($parsed['pet'] ?? false))) {
                    return PhotoModerationResult::rejected(PhotoModerationResult::NO_SUBJECT);
                }
            }

            return PhotoModerationResult::allowed();
        } catch (Throwable $e) {
            // Never break the upload funnel on a transient API problem.
            Log::warning('Photo moderation failed open.', ['message' => $e->getMessage()]);

            return PhotoModerationResult::allowed();
        }
    }
}
