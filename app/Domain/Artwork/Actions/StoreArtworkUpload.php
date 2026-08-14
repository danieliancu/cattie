<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\ArtworkProcessingStage;
use App\Jobs\NormaliseArtworkUpload;
use App\Models\ArtworkSession;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class StoreArtworkUpload
{
    public function __construct(private RecordAnalyticsEvent $analytics) {}

    public function handle(ArtworkSession $session, string $bytes, string $mimeType, ?string $originalName = null, bool $dispatch = true): Upload
    {
        $dimensions = @getimagesizefromstring($bytes);
        if ($dimensions === false || ! in_array($dimensions['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('The uploaded photo is not a supported image.');
        }

        $extension = match ($dimensions['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $key = 'artwork/originals/'.bin2hex(random_bytes(24)).'.'.$extension;
        if (! Storage::disk('local')->put($key, $bytes)) {
            throw new RuntimeException('The uploaded photo could not be stored.');
        }

        try {
            $upload = $session->uploads()->create([
                'user_id' => $session->user_id,
                'guest_token' => null,
                'disk' => 'local',
                'storage_key' => $key,
                'original_name' => $originalName,
                'mime_type' => $dimensions['mime'],
                'size_bytes' => strlen($bytes),
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'sha256' => hash('sha256', $bytes),
                'expires_at' => $session->expires_at,
            ]);
            $session->update(['current_upload_id' => $upload->id, 'status' => ArtworkSessionStatus::PreparingPhoto, 'processing_stage' => ArtworkProcessingStage::PreparingPhoto]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($key);
            throw $e;
        }

        $this->analytics->handle('photo_uploaded', $upload);
        if ($dispatch) {
            NormaliseArtworkUpload::dispatch($upload);
        }

        return $upload;
    }
}
