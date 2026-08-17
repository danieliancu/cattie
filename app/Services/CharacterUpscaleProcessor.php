<?php

namespace App\Services;

use App\Contracts\ImageUpscaler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Raises a transparent character PNG to a real high resolution so wall-print characters
 * stay crisp at A3/A2 print sizes instead of being a bicubic blow-up of the ~1024px AI
 * output. Prefers the pluggable ImageUpscaler (Real-ESRGAN / Pillow LANCZOS); when that is
 * not available it falls back to an in-process, alpha-preserving progressive GD upscale
 * (repeated ~1.5× steps), which is far cleaner than a single large resample.
 */
final class CharacterUpscaleProcessor
{
    public function __construct(private ?ImageUpscaler $upscaler = null) {}

    /** Returns PNG bytes of the character upscaled towards $targetLongEdge (alpha preserved). */
    public function process(string $pngBytes, int $targetLongEdge): string
    {
        $size = @getimagesizefromstring($pngBytes);
        if ($size === false || $targetLongEdge < 1) {
            return $pngBytes;
        }
        if (max($size[0], $size[1]) >= $targetLongEdge) {
            return $pngBytes; // already at least the target resolution
        }

        return $this->viaRunner($pngBytes, $targetLongEdge)
            ?? $this->viaGd($pngBytes, $size[0], $size[1], $targetLongEdge);
    }

    private function viaRunner(string $pngBytes, int $target): ?string
    {
        $token = bin2hex(random_bytes(12));
        $input = "artwork/tmp/{$token}-up-in.png";
        $output = "artwork/tmp/{$token}-up-out.png";
        Storage::disk('local')->put($input, $pngBytes);

        try {
            ($this->upscaler ?? app(ImageUpscaler::class))->upscale(
                Storage::disk('local')->path($input),
                Storage::disk('local')->path($output),
                $target,
            );
            $bytes = Storage::disk('local')->get($output);

            return ($bytes !== null && @getimagesizefromstring($bytes) !== false) ? $bytes : null;
        } catch (Throwable $e) {
            Log::info('Character upscaler runner unavailable; using the GD fallback.', ['exception' => $e::class]);

            return null;
        } finally {
            Storage::disk('local')->delete([$input, $output]);
        }
    }

    private function viaGd(string $pngBytes, int $width, int $height, int $target): string
    {
        $image = @imagecreatefromstring($pngBytes);
        if (! $image instanceof \GdImage) {
            return $pngBytes;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $longEdge = max($width, $height);
        while ($longEdge < $target) {
            $factor = min(1.5, $target / $longEdge);
            $newWidth = max(1, (int) round($width * $factor));
            $newHeight = max(1, (int) round($height * $factor));
            $step = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($step, false);
            imagesavealpha($step, true);
            imagefill($step, 0, 0, imagecolorallocatealpha($step, 0, 0, 0, 127));
            imagecopyresampled($step, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $step;
            $width = $newWidth;
            $height = $newHeight;
            $longEdge = max($width, $height);
        }

        ob_start();
        imagepng($image, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
