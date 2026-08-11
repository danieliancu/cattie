<?php

namespace App\Jobs;

use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationRequest;
use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Exceptions\ImageGenerationException;
use App\Models\Generation;
use App\Services\AiGenerationCostCalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

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

    public function handle(ImageGenerationProvider $provider, AiGenerationCostCalculator $costs, RecordAnalyticsEvent $analytics): void
    {
        $generation = Generation::query()->findOrFail($this->generation->id);
        if (! in_array($generation->status, [GenerationStatus::Queued, GenerationStatus::Pending], true) || $generation->assets()->exists()) {
            return;
        }$generation->update(['status' => GenerationStatus::Processing, 'attempt_count' => $generation->attempt_count + 1, 'started_at' => now()]);
        $input = $generation->upload->assets()->where('kind', 'ai_input')->firstOrFail();
        try {
            $result = $provider->generate(new ImageGenerationRequest($generation->id, $generation->resolved_prompt, Storage::disk($input->disk)->path($input->storage_key), $input->mime_type, $generation->model, $generation->quality, $generation->output_size, $generation->candidate_count, (int) ($generation->parameters['generation_sequence'] ?? 1)));
            $key = 'artwork/generated/'.bin2hex(random_bytes(20)).'.png';
            Storage::disk('local')->put($key, $result->contents);
            $asset = $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => $result->mimeType, 'size_bytes' => strlen($result->contents)]);
            $previewImage = @imagecreatefromstring($result->contents);
            $previewBytes = $result->contents;
            $previewMime = $result->mimeType;
            $previewExtension = 'png';
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
            $previewKey = 'artwork/previews/'.bin2hex(random_bytes(20)).'.'.$previewExtension;
            Storage::disk('local')->put($previewKey, $previewBytes);
            $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => $previewKey, 'mime_type' => $previewMime, 'size_bytes' => strlen($previewBytes)]);
            $cost = $costs->calculate($generation->model, $generation->quality, $generation->output_size, $result->usage);
            $generation->update(['status' => GenerationStatus::Succeeded, 'provider_request_id' => $result->requestId, 'usage_metadata' => $result->usage, 'parameters' => $result->metadata, 'cost_micros' => $cost['micros'], 'cost_currency' => $cost['currency'], 'cost_basis' => $cost['basis'], 'pricing_version' => $cost['pricing_version'], 'completed_at' => now()]);
            $generation->artworkSession->update(['status' => ArtworkSessionStatus::PreviewReady]);
            $analytics->handle('generation_succeeded', $generation);
        } catch (ImageGenerationException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $generation->update(['status' => GenerationStatus::Queued, 'failure_reason' => $e->getMessage(), 'failure_category' => $e->category, 'provider_error_code' => $e->providerCode, 'is_retryable' => true]);
                throw $e;
            }
            $generation->update(['status' => GenerationStatus::Failed, 'failure_reason' => $e->getMessage(), 'failure_category' => $e->category, 'provider_error_code' => $e->providerCode, 'is_retryable' => $e->retryable, 'completed_at' => now()]);
            $generation->artworkSession->update(['status' => ArtworkSessionStatus::Failed]);
            $analytics->handle('generation_failed', $generation);
        }
    }
}
