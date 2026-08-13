<?php

namespace App\Jobs;

use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationReference;
use App\Data\ImageGenerationRequest;
use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Exceptions\ImageGenerationException;
use App\Models\Generation;
use App\Services\AiGenerationCostCalculator;
use App\Services\BackgroundRemovalProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateArtwork implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public $timeout = 240;

    public function __construct(public Generation $generation) {}

    public function uniqueId(): string
    {
        return $this->generation->id;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->generation->id))->expireAfter(300)];
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ImageGenerationProvider $provider, AiGenerationCostCalculator $costs, RecordAnalyticsEvent $analytics, ?RenderComposedDesign $renderDesign = null, ?BackgroundRemovalProcessor $backgroundRemoval = null): void
    {
        $generation = Generation::query()->findOrFail($this->generation->id);
        if (! in_array($generation->status, [GenerationStatus::Queued, GenerationStatus::Pending], true) || $generation->status === GenerationStatus::Succeeded) {
            return;
        }
        if ($generation->assets()->where('kind', 'composition_source')->exists() || $generation->assets()->where('kind', 'web_preview')->exists()) {
            return;
        }

        $generation->update(['status' => GenerationStatus::Processing, 'attempt_count' => $generation->attempt_count + 1, 'started_at' => now()]);
        $input = $generation->upload->assets()->where('kind', 'ai_input')->firstOrFail();
        $storedKeys = [];
        try {
            $requirements = (array) ($generation->parameters['output_requirements'] ?? []);
            $references = collect($generation->parameters['input_references'] ?? [])->map(function (array $frozen): ImageGenerationReference {
                $configured = config('artwork.style_references.'.($frozen['key'] ?? ''));
                if (! is_array($configured)
                    || ($configured['role'] ?? null) !== ($frozen['role'] ?? null)
                    || ($configured['mime'] ?? null) !== ($frozen['mime'] ?? null)
                    || ! hash_equals((string) ($frozen['sha256'] ?? ''), (string) ($configured['sha256'] ?? ''))
                    || ! is_file((string) ($configured['path'] ?? ''))
                    || ! hash_equals((string) $configured['sha256'], (string) hash_file('sha256', $configured['path']))) {
                    throw new ImageGenerationException('The configured style reference is unavailable.', 'configuration', 'invalid_style_reference', false);
                }

                return new ImageGenerationReference($frozen['key'], $frozen['role'], $configured['path'], $frozen['mime'], $frozen['sha256']);
            })->values()->all();
            $result = $provider->generate(new ImageGenerationRequest($generation->id, $generation->resolved_prompt, Storage::disk($input->disk)->path($input->storage_key), $input->mime_type, $generation->model, $generation->quality, $generation->output_size, $generation->candidate_count, (int) ($generation->parameters['generation_sequence'] ?? 1), $requirements, $references));
            $sourceSize = @getimagesizefromstring($result->contents);
            if ($sourceSize === false) {
                throw new ImageGenerationException('Generated artwork is not a valid image.', 'invalid_response', 'malformed_image', false);
            }
            $processed = ($backgroundRemoval ?? app(BackgroundRemovalProcessor::class))->process($result->contents, $requirements);

            $previewImage = @imagecreatefromstring($processed['contents']);
            $previewBytes = $processed['contents'];
            $previewMime = $processed['mime_type'];
            $previewExtension = $previewMime === 'image/png' ? 'png' : 'webp';
            $previewWidth = $processed['width'];
            $previewHeight = $processed['height'];
            if ($previewImage) {
                $width = imagesx($previewImage);
                $height = imagesy($previewImage);
                $scale = min(1, 1400 / max($width, $height));
                $previewWidth = (int) round($width * $scale);
                $previewHeight = (int) round($height * $scale);
                $canvas = imagecreatetruecolor($previewWidth, $previewHeight);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);
                imagecopyresampled($canvas, $previewImage, 0, 0, 0, 0, $previewWidth, $previewHeight, $width, $height);
                ob_start();
                imagewebp($canvas, null, 88);
                $previewBytes = ob_get_clean();
                imagedestroy($canvas);
                imagedestroy($previewImage);
                $previewMime = 'image/webp';
                $previewExtension = 'webp';
            }

            $token = bin2hex(random_bytes(20));
            $originalKey = "artwork/generated/{$token}-original.png";
            $compositionKey = "artwork/generated/{$token}-composition.png";
            $previewKey = "artwork/previews/{$token}.{$previewExtension}";
            foreach ([$originalKey => $result->contents, $compositionKey => $processed['contents'], $previewKey => $previewBytes] as $key => $bytes) {
                if (! Storage::disk('local')->put($key, $bytes)) {
                    throw new ImageGenerationException('Generated artwork could not be stored.', 'storage', 'asset_write_failed', true);
                }
                $storedKeys[] = $key;
            }

            $cost = $costs->calculate($generation->model, $generation->quality, $generation->output_size, $result->usage);
            $asset = DB::transaction(function () use ($generation, $result, $sourceSize, $processed, $previewBytes, $previewMime, $previewWidth, $previewHeight, $originalKey, $compositionKey, $previewKey, $cost) {
                $generation->assets()->delete();
                $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $originalKey, 'mime_type' => $result->mimeType, 'size_bytes' => strlen($result->contents), 'width' => $sourceSize[0], 'height' => $sourceSize[1]]);
                $composition = $generation->assets()->create(['kind' => 'composition_source', 'disk' => 'local', 'storage_key' => $compositionKey, 'mime_type' => $processed['mime_type'], 'size_bytes' => strlen($processed['contents']), 'width' => $processed['width'], 'height' => $processed['height']]);
                $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => $previewKey, 'mime_type' => $previewMime, 'size_bytes' => strlen($previewBytes), 'width' => $previewWidth, 'height' => $previewHeight]);
                $generation->update(['status' => GenerationStatus::Succeeded, 'provider_request_id' => $result->requestId, 'usage_metadata' => $result->usage, 'parameters' => array_merge($generation->parameters ?? [], ['provider' => $result->metadata, 'background_removed' => $processed['processed']]), 'cost_micros' => $cost['micros'], 'cost_currency' => $cost['currency'], 'cost_basis' => $cost['basis'], 'pricing_version' => $cost['pricing_version'], 'failure_reason' => null, 'failure_category' => null, 'provider_error_code' => null, 'is_retryable' => false, 'completed_at' => now()]);

                return $composition;
            });
            $storedKeys = [];
            unset($previewBytes, $result);
            gc_collect_cycles();
            if ($generation->artworkSession->product->designTemplate) {
                ($renderDesign ?? app(RenderComposedDesign::class))->handle($generation->artworkSession, $asset);
            }
            $generation->artworkSession->update(['status' => ArtworkSessionStatus::PreviewReady]);
            $analytics->handle('generation_succeeded', $generation);
        } catch (ImageGenerationException $e) {
            if ($storedKeys !== []) {
                Storage::disk('local')->delete($storedKeys);
            }
            if ($e->retryable && $this->attempts() < $this->tries) {
                $generation->update(['status' => GenerationStatus::Queued, 'failure_reason' => $e->getMessage(), 'failure_category' => $e->category, 'provider_error_code' => $e->providerCode, 'is_retryable' => true]);
                throw $e;
            }
            $generation->update(['status' => GenerationStatus::Failed, 'failure_reason' => $e->getMessage(), 'failure_category' => $e->category, 'provider_error_code' => $e->providerCode, 'is_retryable' => $e->retryable, 'completed_at' => now()]);
            $generation->artworkSession->update(['status' => ArtworkSessionStatus::Failed]);
            $analytics->handle('generation_failed', $generation);
        } catch (Throwable $e) {
            if ($storedKeys !== []) {
                Storage::disk('local')->delete($storedKeys);
            }
            $generation->update(['status' => GenerationStatus::Failed, 'failure_reason' => 'Unexpected generation failure.', 'failure_category' => 'internal', 'provider_error_code' => null, 'is_retryable' => false, 'completed_at' => now()]);
            $generation->artworkSession->update(['status' => ArtworkSessionStatus::Failed]);
            Log::error('Artwork generation failed unexpectedly.', ['artwork_session_id' => $generation->artwork_session_id, 'generation_id' => $generation->id, 'exception' => $e::class]);
            $analytics->handle('generation_failed', $generation);
        }
    }
}
