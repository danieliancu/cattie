<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ComposedDesignStatus;
use App\Models\ArtworkSession;
use App\Models\ComposedDesign;
use App\Models\GenerationAsset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RenderComposedDesign
{
    private const PREVIEW_MAX_EDGE = 1200;

    public function handle(ArtworkSession $session, GenerationAsset $asset, array $characterAdjustments = []): ComposedDesign
    {
        $session->loadMissing(['product.designTemplate', 'variant']);
        $template = $session->product?->designTemplate;
        if (! $template || ! $session->variant || $asset->generation?->artwork_session_id !== $session->id) {
            throw new RuntimeException('The design inputs are incomplete.');
        }

        $definition = $template->definition();
        $printArea = $definition['output_size']['print_area'] ?? 'default';
        $size = $session->variant->requiredPrintResolution('prodigi', $printArea);
        $personalisation = collect($session->personalisation_snapshot)->keyBy('key');
        $sourceBytes = Storage::disk($asset->disk)->get($asset->storage_key);
        $source = $sourceBytes === null ? false : @imagecreatefromstring($sourceBytes);
        unset($sourceBytes);
        if (! $source) {
            throw new RuntimeException('The generation asset is unreadable.');
        }

        $canvas = imagecreatetruecolor($size['width'], $size['height']);
        if (! $canvas) {
            imagedestroy($source);
            throw new RuntimeException('The design canvas could not be created.');
        }

        try {
            $editorBackgroundBytes = null;
            foreach ($definition['layers'] as $layer) {
                if (($layer['type'] ?? null) === 'generation_asset') {
                    $editorBackgroundBytes = $this->previewBytes($canvas);
                }
                match ($layer['type'] ?? null) {
                    'solid' => $this->renderSolid($canvas, $layer, $session->variant->options ?? []),
                    'personalisation_text_pattern' => $this->renderTextPattern($canvas, $layer, $definition, $personalisation->all()),
                    'generation_asset' => $this->renderGenerationAsset($canvas, $source, $layer, $definition[$layer['config'] ?? ''] ?? [], $characterAdjustments),
                    default => throw new RuntimeException('The design template contains an unsupported layer.'),
                };
            }

            ob_start();
            if (! imagepng($canvas, null, 6)) {
                throw new RuntimeException('The full design could not be encoded.');
            }
            $fullBytes = ob_get_clean();
            $previewBytes = $this->previewBytes($canvas);
        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            imagedestroy($source);
            imagedestroy($canvas);
        }

        $directory = "artwork-sessions/{$session->public_id}/composed-designs";
        $token = bin2hex(random_bytes(20));
        $fullKey = "{$directory}/{$token}.png";
        $previewKey = "{$directory}/{$token}-preview.webp";
        $editorBackgroundKey = "{$directory}/{$token}-editor-background.webp";
        if (! Storage::disk('local')->put($fullKey, $fullBytes) || ! Storage::disk('local')->put($previewKey, $previewBytes) || ! $editorBackgroundBytes || ! Storage::disk('local')->put($editorBackgroundKey, $editorBackgroundBytes)) {
            Storage::disk('local')->delete([$fullKey, $previewKey, $editorBackgroundKey]);
            throw new RuntimeException('The composed design could not be stored.');
        }

        try {
            return $session->composedDesigns()->create([
                'product_variant_id' => $session->product_variant_id,
                'generation_asset_id' => $asset->id,
                'product_design_template_id' => $template->id,
                'template_version' => $template->version,
                'personalisation_snapshot' => $session->personalisation_snapshot,
                'character_adjustments' => $characterAdjustments,
                'width' => $size['width'], 'height' => $size['height'], 'format' => 'png',
                'disk' => 'local', 'storage_key' => $fullKey, 'preview_storage_key' => $previewKey, 'editor_background_storage_key' => $editorBackgroundKey,
                'status' => ComposedDesignStatus::Ready,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete([$fullKey, $previewKey, $editorBackgroundKey]);
            throw $e;
        }
    }

    /** @return array{x:int,y:int,width:int,height:int} */
    public function pixelRect(array $layer, int $width, int $height): array
    {
        return [
            'x' => (int) round(($layer['x'] ?? 0) * $width),
            'y' => (int) round(($layer['y'] ?? 0) * $height),
            'width' => (int) round(($layer['max_width'] ?? $layer['width'] ?? 1) * $width),
            'height' => (int) round(($layer['max_height'] ?? $layer['height'] ?? 1) * $height),
        ];
    }

    /** @return array{width:int,height:int} */
    public function containedSize(int $sourceWidth, int $sourceHeight, int $boxWidth, int $boxHeight): array
    {
        if (min($sourceWidth, $sourceHeight, $boxWidth, $boxHeight) < 1) {
            throw new RuntimeException('Contain dimensions must be positive.');
        }
        $scale = min($boxWidth / $sourceWidth, $boxHeight / $sourceHeight);

        return ['width' => max(1, (int) round($sourceWidth * $scale)), 'height' => max(1, (int) round($sourceHeight * $scale))];
    }

    /** @return array{x:int,y:int,width:int,height:int} */
    public function characterBox(array $config, int $canvasWidth, int $canvasHeight): array
    {
        $scale = max(.1, min(2, (float) ($config['scale'] ?? 1)));
        $boxWidth = min($canvasWidth, max(1, (int) round($canvasWidth * (float) ($config['max_width'] ?? .4) * $scale)));
        $boxHeight = min($canvasHeight, max(1, (int) round($canvasHeight * (float) ($config['max_height'] ?? .84) * $scale)));
        $centreX = $canvasWidth * ((float) ($config['x'] ?? .5) + (float) ($config['offset_x'] ?? 0));
        $centreY = $canvasHeight * ((float) ($config['y'] ?? .5) + (float) ($config['offset_y'] ?? 0));

        return [
            'x' => max(0, min($canvasWidth - $boxWidth, (int) round($centreX - ($boxWidth / 2)))),
            'y' => max(0, min($canvasHeight - $boxHeight, (int) round($centreY - ($boxHeight / 2)))),
            'width' => $boxWidth,
            'height' => $boxHeight,
        ];
    }

    private function renderSolid(\GdImage $canvas, array $layer, array $variantOptions): void
    {
        $colour = $layer['colour'] ?? null;
        if ($option = $layer['variant_option'] ?? null) {
            $value = mb_strtolower(trim((string) ($variantOptions[$option] ?? '')));
            $colour = $layer['colours_by_variant'][$value] ?? $layer['fallback_colour'] ?? null;
        }
        imagefill($canvas, 0, 0, $this->colour($canvas, $colour ?? '#ffffff'));
    }

    private function renderTextPattern(\GdImage $canvas, array $layer, array $definition, array $personalisation): void
    {
        $value = trim((string) ($personalisation[$layer['field'] ?? '']['value'] ?? ''));
        if ($value === '') {
            return;
        }
        $zone = collect($definition['safe_zones'])->firstWhere('id', $layer['safe_zone'] ?? null);
        if (! $zone) {
            throw new RuntimeException('The text safe zone is missing.');
        }
        $rect = $this->pixelRect($zone, imagesx($canvas), imagesy($canvas));
        $styles = $layer['styles'] ?? [];
        $items = $layer['items'] ?? [];
        if (! is_array($styles) || $styles === [] || ! is_array($items) || $items === []) {
            throw new RuntimeException('The text pattern configuration is invalid.');
        }
        $colour = $this->colour($canvas, $layer['colour'] ?? '#ffffff');
        imagesetclip($canvas, $rect['x'], $rect['y'], $rect['x'] + $rect['width'], $rect['y'] + $rect['height']);
        foreach ($items as $item) {
            $style = $styles[$item['style'] ?? ''] ?? null;
            if (! is_array($style)) {
                throw new RuntimeException('The text pattern references an invalid style.');
            }
            $font = $this->fontPath((string) ($style['font_family'] ?? 'serif'));
            $fontSize = max(18, (int) round(min(imagesx($canvas), imagesy($canvas)) * (float) ($item['size'] ?? .04)));
            $rotation = (float) ($item['rotation'] ?? 0);
            $text = ($style['uppercase'] ?? false) ? mb_strtoupper($value) : $value;
            $bounds = imagettfbbox($fontSize, $rotation, $font, $text);
            if ($bounds === false) {
                throw new RuntimeException('The personalised text could not be measured.');
            }
            $textWidth = max($bounds[0], $bounds[2], $bounds[4], $bounds[6]) - min($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
            $textHeight = max($bounds[1], $bounds[3], $bounds[5], $bounds[7]) - min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
            $centreX = $rect['x'] + ((float) ($item['x'] ?? 0) * $rect['width']);
            $centreY = $rect['y'] + ((float) ($item['y'] ?? 0) * $rect['height']);
            $x = (int) round($centreX - ($textWidth / 2) - min($bounds[0], $bounds[2], $bounds[4], $bounds[6]));
            $y = (int) round($centreY - ($textHeight / 2) - min($bounds[1], $bounds[3], $bounds[5], $bounds[7]));
            imagettftext($canvas, $fontSize, $rotation, $x, $y, $colour, $font, $text);
        }
        imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
    }

    private function renderGenerationAsset(\GdImage $canvas, \GdImage $source, array $layer, array $config, array $adjustments = []): void
    {
        if (($layer['fit'] ?? null) !== 'contain') {
            throw new RuntimeException('Generation artwork must use contain fitting.');
        }
        $rect = $this->characterBox($config, imagesx($canvas), imagesy($canvas));
        $contained = $this->containedSize(imagesx($source), imagesy($source), $rect['width'], $rect['height']);
        $scale = max(.6, min(1.8, (float) ($adjustments['scale'] ?? 1)));
        $width = max(1, (int) round($contained['width'] * $scale));
        $height = max(1, (int) round($contained['height'] * $scale));
        $offsetX = max(-.2, min(.2, (float) ($adjustments['offset_x'] ?? 0))) * imagesx($canvas);
        $offsetY = max(-.2, min(.2, (float) ($adjustments['offset_y'] ?? 0))) * imagesy($canvas);
        $x = $rect['x'] + (int) round(($rect['width'] - $width) / 2 + $offsetX);
        $y = $rect['y'] + (int) round(($rect['height'] - $height) / 2 + $offsetY);
        imagesetclip($canvas, $rect['x'], $rect['y'], $rect['x'] + $rect['width'] - 1, $rect['y'] + $rect['height'] - 1);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
    }

    private function previewBytes(\GdImage $canvas): string
    {
        $scale = min(1, self::PREVIEW_MAX_EDGE / max(imagesx($canvas), imagesy($canvas)));
        $width = max(1, (int) round(imagesx($canvas) * $scale));
        $height = max(1, (int) round(imagesy($canvas) * $scale));
        $preview = imagecreatetruecolor($width, $height);
        imagecopyresampled($preview, $canvas, 0, 0, 0, 0, $width, $height, imagesx($canvas), imagesy($canvas));
        ob_start();
        imagewebp($preview, null, 86);
        $bytes = ob_get_clean();
        imagedestroy($preview);

        return $bytes;
    }

    private function colour(\GdImage $image, string $hex): int
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $match)) {
            throw new RuntimeException('The design template contains an invalid colour.');
        }
        $value = hexdec($match[1]);

        return imagecolorallocate($image, ($value >> 16) & 255, ($value >> 8) & 255, $value & 255);
    }

    /** @return array{script:string,serif:string,sans-bold:string} */
    public function resolvedFontPaths(): array
    {
        return [
            'script' => $this->fontPath('script'),
            'serif' => $this->fontPath('serif'),
            'sans-bold' => $this->fontPath('sans-bold'),
        ];
    }

    private function fontPath(string $family): string
    {
        $candidates = match ($family) {
            'script' => [resource_path('fonts/Cattie-Script.ttf'), 'C:/Windows/Fonts/segoesc.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Italic.ttf'],
            'sans-bold' => [resource_path('fonts/Cattie-Sans-Bold.ttf'), 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'],
            'serif' => [resource_path('fonts/Cattie-Serif-Bold.ttf'), 'C:/Windows/Fonts/georgiab.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf'],
            default => throw new RuntimeException("Unsupported design font family [{$family}]."),
        };
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("No offline font is available for [{$family}].");
    }
}
