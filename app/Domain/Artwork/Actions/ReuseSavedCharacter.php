<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkProcessingStage;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\SavedCharacter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ReuseSavedCharacter
{
    public function __construct(private RenderComposedDesign $renderer, private RecordAnalyticsEvent $analytics) {}

    public function handle(ArtworkSession $session, SavedCharacter $character)
    {
        if ($session->user_id !== $character->user_id || ! $session->product->artworkStyles()->whereKey($character->artwork_style_id)->exists()) {
            throw new RuntimeException('That saved character is not available for this product.');
        }
        $png = Storage::disk($character->disk)->get($character->storage_key);
        $webp = Storage::disk($character->disk)->get($character->preview_storage_key);
        if (! is_string($png) || ! is_string($webp) || $png === '' || $webp === '') {
            throw new RuntimeException('That saved character is unavailable.');
        }

        $token = bin2hex(random_bytes(20));
        $directory = "artwork-sessions/{$session->public_id}/generations/{$token}";
        $pngKey = "{$directory}/composition.png";
        $webpKey = "{$directory}/preview.webp";
        if (! Storage::disk('local')->put($pngKey, $png) || ! Storage::disk('local')->put($webpKey, $webp)) {
            Storage::disk('local')->deleteDirectory($directory);
            throw new RuntimeException('The saved character could not be prepared.');
        }

        try {
            [$generation, $asset] = DB::transaction(function () use ($session, $character, $png, $webp, $pngKey, $webpKey) {
                $session = ArtworkSession::query()->lockForUpdate()->findOrFail($session->id);
                $generation = $session->generations()->create([
                    'upload_id' => null,
                    'saved_character_id' => $character->id,
                    'product_id' => $session->product_id,
                    'product_variant_id' => $session->product_variant_id,
                    'artwork_style_id' => $character->artwork_style_id,
                    'prompt_key' => 'saved-character-reuse',
                    'prompt_version' => 1,
                    'resolved_prompt' => 'Local saved character reuse; no AI prompt.',
                    'provider' => 'saved-character',
                    'model' => 'local-reuse',
                    'candidate_count' => 1,
                    'idempotency_key' => (string) Str::uuid(),
                    'status' => GenerationStatus::Succeeded,
                    'parameters' => ['source' => 'saved_character'],
                    'cost_micros' => 0,
                    'cost_currency' => 'GBP',
                    'is_retryable' => false,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
                $asset = $generation->assets()->create(['kind' => 'composition_source', 'disk' => 'local', 'storage_key' => $pngKey, 'mime_type' => 'image/png', 'size_bytes' => strlen($png), 'width' => $character->width, 'height' => $character->height]);
                $size = @getimagesizefromstring($webp);
                $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => $webpKey, 'mime_type' => 'image/webp', 'size_bytes' => strlen($webp), 'width' => $size[0] ?? $character->width, 'height' => $size[1] ?? $character->height]);
                $session->approvedAsset?->update(['is_selected' => false, 'selected_at' => null]);
                $session->update([
                    'artwork_style_id' => $character->artwork_style_id,
                    'current_generation_id' => $generation->id,
                    'approved_generation_asset_id' => null,
                    'approved_composed_design_id' => null,
                    'approved_at' => null,
                    'status' => ArtworkSessionStatus::PreviewReady,
                    'processing_stage' => ArtworkProcessingStage::Ready,
                    'expires_at' => now()->addDays(config('artwork.retention_days')),
                ]);
                if ($session->product->designTemplate) {
                    $this->renderer->handlePreview($session->fresh(), $asset);
                }

                return [$generation, $asset];
            });
        } catch (Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        }

        $this->analytics->handle('saved_character_selected', $character, ['artwork_session_id' => $session->id, 'generation_id' => $generation->id]);

        return $generation;
    }
}
