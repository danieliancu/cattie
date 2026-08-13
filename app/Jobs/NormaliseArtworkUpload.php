<?php

namespace App\Jobs;

use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Enums\ArtworkSessionStatus;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class NormaliseArtworkUpload implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public function __construct(public Upload $upload, public bool $dispatchGeneration = true) {}

    public function handle(RequestArtworkGeneration $request): void
    {
        $source = Storage::disk($this->upload->disk)->get($this->upload->storage_key);
        $image = @imagecreatefromstring($source);
        if (! $image) {
            throw new \RuntimeException('Image cannot be decoded.');
        }
        if ($this->upload->mime_type === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data(Storage::disk($this->upload->disk)->path($this->upload->storage_key));
            $image = match ($exif['Orientation'] ?? 1) {
                3 => imagerotate($image, 180, 0), 6 => imagerotate($image, -90, 0), 8 => imagerotate($image, 90, 0), default => $image,
            };
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $max = config('artwork.upload.normalised_max_dimension');
        $scale = min(1, $max / max($width, $height));
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);
        $canvas = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
        ob_start();
        imagewebp($canvas, null, 92);
        $bytes = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($image);
        $key = 'artwork/normalised/'.bin2hex(random_bytes(20)).'.webp';
        Storage::disk('local')->put($key, $bytes);
        $this->upload->assets()->create(['kind' => 'ai_input', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/webp', 'size_bytes' => strlen($bytes), 'width' => $newW, 'height' => $newH]);
        unset($source, $bytes);
        gc_collect_cycles();
        $session = $this->upload->artworkSession;
        $request->handle($session, false, $this->dispatchGeneration);
    }

    public function failed(): void
    {
        $this->upload->artworkSession?->update(['status' => ArtworkSessionStatus::Failed]);
    }
}
