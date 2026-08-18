<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ComposedDesignStatus;
use App\Models\ArtworkSession;
use App\Models\ComposedDesign;
use App\Models\GenerationAsset;
use App\Models\ProductDesignTemplate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RenderComposedDesign
{
    public function __construct(private ResolveDesignManifest $manifests) {}

    private const PREVIEW_MAX_EDGE = 1200;

    public function handle(ArtworkSession $session, GenerationAsset $asset, array $characterAdjustments = []): ComposedDesign
    {
        return $this->render($session, $asset, $characterAdjustments, true);
    }

    public function handlePreview(ArtworkSession $session, GenerationAsset $asset, array $characterAdjustments = []): ComposedDesign
    {
        return $this->render($session, $asset, $characterAdjustments, false);
    }

    private function render(ArtworkSession $session, GenerationAsset $asset, array $characterAdjustments, bool $fullResolution): ComposedDesign
    {
        $session->loadMissing(['product.designTemplate', 'variant']);
        $template = $session->product?->designTemplate;
        if (! $template || ! $session->variant || $asset->generation?->artwork_session_id !== $session->id) {
            throw new RuntimeException('The design inputs are incomplete.');
        }

        $manifest = $this->manifests->handle($session);
        $definition = $manifest['configuration'];
        $characterAdjustments = $this->constrainCharacterAdjustments(
            $characterAdjustments,
            $definition['character'] ?? [],
            $definition['character']['adjustment_limits'] ?? [],
        );
        $printArea = $definition['output_size']['print_area'] ?? 'default';
        $size = $session->variant->requiredPrintResolution($printArea);
        // The source asset is decoded at full print scale on BOTH paths — the preview only
        // downscales the canvas, never the source — so budget memory from the full print
        // resolution before the decode below. Wall prints upscale the character to a 4096px
        // long edge, whose decode alone (~44 MB) tips a default 128 MB limit; the preview
        // canvas (~1 MP) would keep the guard silent, so it must run on the full size here.
        $this->ensureMemoryForCanvas($size['width'], $size['height']);
        if (! $fullResolution) {
            $scale = min(1, self::PREVIEW_MAX_EDGE / max($size['width'], $size['height']));
            $size = [
                'width' => max(1, (int) round($size['width'] * $scale)),
                'height' => max(1, (int) round($size['height'] * $scale)),
            ];
        }
        $personalisation = collect($session->personalisation_snapshot)->keyBy('key');
        $sourceBytes = Storage::disk($asset->disk)->get($asset->storage_key);
        $source = $sourceBytes === null ? false : @imagecreatefromstring($sourceBytes);
        unset($sourceBytes);
        if (! $source) {
            throw new RuntimeException('The generation asset is unreadable.');
        }

        // Wall-print composition draws a real raster background (the photo-derived or
        // AI-linked context) beneath the character. It is a sibling asset on the same
        // generation; a missing one degrades to the template's fallback colour rather
        // than failing the render.
        $background = null;
        $backgroundAsset = $asset->generation?->assets()->where('kind', 'context_background')->first();
        if ($backgroundAsset) {
            $backgroundBytes = Storage::disk($backgroundAsset->disk)->get($backgroundAsset->storage_key);
            $background = $backgroundBytes === null ? null : @imagecreatefromstring($backgroundBytes);
            unset($backgroundBytes);
        }

        $canvas = imagecreatetruecolor($size['width'], $size['height']);
        if (! $canvas) {
            imagedestroy($source);
            throw new RuntimeException('The design canvas could not be created.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $editorScale = min(1, self::PREVIEW_MAX_EDGE / max($size['width'], $size['height']));
        $editorWidth = max(1, (int) round($size['width'] * $editorScale));
        $editorHeight = max(1, (int) round($size['height'] * $editorScale));
        $editorCanvas = imagecreatetruecolor($editorWidth, $editorHeight);
        if (! $editorCanvas) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('The editor canvas could not be created.');
        }
        imagealphablending($editorCanvas, false);
        imagesavealpha($editorCanvas, true);
        imagefill($editorCanvas, 0, 0, imagecolorallocatealpha($editorCanvas, 0, 0, 0, 127));
        imagealphablending($editorCanvas, true);

        try {
            $editorBackgroundBytes = null;
            $variantOptions = $session->variant->options ?? [];
            foreach ($definition['layers'] as $layer) {
                $isCharacter = ($layer['type'] ?? null) === 'generation_asset';
                if ($isCharacter) {
                    // The editor background is the composed surface WITHOUT the character, so
                    // snapshot the editor canvas before the character layer is drawn (the
                    // generation asset is only ever painted onto the full print canvas).
                    $editorBackgroundBytes = $this->previewBytes($editorCanvas);
                }
                $this->applyLayer($canvas, $layer, $definition, $source, $background, $personalisation, $template, $variantOptions, $characterAdjustments);
                if (! $isCharacter) {
                    $this->applyLayer($editorCanvas, $layer, $definition, $source, $background, $personalisation, $template, $variantOptions, $characterAdjustments);
                }
            }

            $fullBytes = null;
            if ($fullResolution) {
                if (! imageresolution($canvas, $size['dpi'], $size['dpi'])) {
                    throw new RuntimeException('The print resolution metadata could not be set.');
                }
                ob_start();
                if (! imagepng($canvas, null, 6)) {
                    throw new RuntimeException('The full design could not be encoded.');
                }
                $fullBytes = ob_get_clean();
            }
            $previewBytes = $this->previewBytes($canvas);
        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            imagedestroy($source);
            imagedestroy($canvas);
            imagedestroy($editorCanvas);
            if ($background instanceof \GdImage) {
                imagedestroy($background);
            }
        }

        $directory = "artwork-sessions/{$session->public_id}/composed-designs";
        $token = bin2hex(random_bytes(20));
        $fullKey = $fullResolution ? "{$directory}/{$token}.png" : null;
        $previewKey = "{$directory}/{$token}-preview.webp";
        $editorBackgroundKey = "{$directory}/{$token}-editor-background.webp";
        $fullStored = ! $fullResolution || ($fullKey && $fullBytes && Storage::disk('local')->put($fullKey, $fullBytes));
        if (! $fullStored || ! Storage::disk('local')->put($previewKey, $previewBytes) || ! $editorBackgroundBytes || ! Storage::disk('local')->put($editorBackgroundKey, $editorBackgroundBytes)) {
            Storage::disk('local')->delete(array_filter([$fullKey, $previewKey, $editorBackgroundKey]));
            throw new RuntimeException('The composed design could not be stored.');
        }

        try {
            $fingerprint = $this->manifests->fingerprint($manifest, $session, $asset, $characterAdjustments);

            return $session->composedDesigns()->create([
                'product_variant_id' => $session->product_variant_id,
                'generation_asset_id' => $asset->id,
                'product_design_template_id' => $template->id,
                'design_template_version_id' => $manifest['template_version_id'],
                'template_version' => $manifest['template_version'],
                'personalisation_snapshot' => $session->personalisation_snapshot,
                'character_adjustments' => $characterAdjustments,
                'resolved_manifest' => $manifest,
                'render_fingerprint' => $fingerprint,
                'width' => $size['width'], 'height' => $size['height'], 'format' => 'png',
                'disk' => 'local', 'storage_key' => $fullKey, 'preview_storage_key' => $previewKey, 'editor_background_storage_key' => $editorBackgroundKey,
                'status' => ComposedDesignStatus::Ready,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_filter([$fullKey, $previewKey, $editorBackgroundKey]));
            throw $e;
        }
    }

    /**
     * Render the full-resolution print PNG for an already-approved design and attach it to
     * the row (`storage_key`). This runs on the queue worker (App\Jobs\RenderComposedDesignPrint)
     * so the heavy full-scale canvas never allocates inside a web request. It composes from
     * the design's own stored manifest and adjustments, so the print matches the approved
     * preview exactly. Idempotent: a design that already has a print file is left untouched.
     */
    public function renderPrintFile(ComposedDesign $design): void
    {
        if ($design->storage_key) {
            return;
        }
        $design->loadMissing(['artworkSession.product.designTemplate', 'variant']);
        $session = $design->artworkSession;
        $template = $session?->product?->designTemplate;
        $definition = $design->resolved_manifest['configuration'] ?? null;
        if (! $session || ! $template || ! $design->variant || ! is_array($definition)) {
            throw new RuntimeException('The approved design inputs are incomplete.');
        }
        $asset = GenerationAsset::query()->with('generation')->findOrFail($design->generation_asset_id);

        $printArea = $definition['output_size']['print_area'] ?? 'default';
        $size = $design->variant->requiredPrintResolution($printArea);
        $this->ensureMemoryForCanvas($size['width'], $size['height']);

        $personalisation = collect($design->personalisation_snapshot)->keyBy('key');
        $characterAdjustments = $design->character_adjustments ?? [];
        $variantOptions = $design->variant->options ?? [];

        $sourceBytes = Storage::disk($asset->disk)->get($asset->storage_key);
        $source = $sourceBytes === null ? false : @imagecreatefromstring($sourceBytes);
        unset($sourceBytes);
        if (! $source) {
            throw new RuntimeException('The generation asset is unreadable.');
        }

        $background = null;
        $backgroundAsset = $asset->generation?->assets()->where('kind', 'context_background')->first();
        if ($backgroundAsset) {
            $backgroundBytes = Storage::disk($backgroundAsset->disk)->get($backgroundAsset->storage_key);
            $background = $backgroundBytes === null ? null : @imagecreatefromstring($backgroundBytes);
            unset($backgroundBytes);
        }

        $canvas = imagecreatetruecolor($size['width'], $size['height']);
        if (! $canvas) {
            imagedestroy($source);
            if ($background instanceof \GdImage) {
                imagedestroy($background);
            }
            throw new RuntimeException('The design canvas could not be created.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        try {
            foreach ($definition['layers'] as $layer) {
                $this->applyLayer($canvas, $layer, $definition, $source, $background, $personalisation, $template, $variantOptions, $characterAdjustments);
            }
            if (! imageresolution($canvas, $size['dpi'], $size['dpi'])) {
                throw new RuntimeException('The print resolution metadata could not be set.');
            }
            ob_start();
            if (! imagepng($canvas, null, 6)) {
                throw new RuntimeException('The full design could not be encoded.');
            }
            $fullBytes = ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            imagedestroy($source);
            imagedestroy($canvas);
            if ($background instanceof \GdImage) {
                imagedestroy($background);
            }
        }

        $key = "artwork-sessions/{$session->public_id}/composed-designs/".bin2hex(random_bytes(20)).'.png';
        if (! $fullBytes || ! Storage::disk('local')->put($key, $fullBytes)) {
            throw new RuntimeException('The composed print file could not be stored.');
        }
        $design->update([
            'storage_key' => $key,
            'width' => $size['width'],
            'height' => $size['height'],
            'format' => 'png',
        ]);
    }

    private function applyLayer(\GdImage $canvas, array $layer, array $definition, \GdImage $source, ?\GdImage $background, \Illuminate\Support\Collection $personalisation, ProductDesignTemplate $template, array $variantOptions, array $characterAdjustments): void
    {
        match ($layer['type'] ?? null) {
            'transparent' => $this->renderTransparent($canvas),
            'context_background' => $this->renderContextBackground($canvas, $background, $definition[$layer['config'] ?? ''] ?? []),
            'personalisation_text_pattern' => $this->renderTextPattern($canvas, $layer, $definition, $personalisation->all(), $template, $variantOptions),
            'generation_asset' => $this->renderGenerationAsset($canvas, $source, $layer, $definition[$layer['config'] ?? ''] ?? [], $characterAdjustments, $definition['character_clip'] ?? ['mode' => 'canvas']),
            default => throw new RuntimeException('The design template contains an unsupported layer.'),
        };
    }

    private function constrainCharacterAdjustments(array $adjustments, array $character, array $limits): array
    {
        if ($adjustments === []) {
            return [];
        }

        $characterX = (float) ($character['x'] ?? .5);
        $characterY = (float) ($character['y'] ?? .5);
        $offsetXMin = max((float) ($limits['offset_x_min'] ?? -1000), -$characterX);
        $offsetXMax = min((float) ($limits['offset_x_max'] ?? 1000), 1 - $characterX);
        $offsetYMin = max((float) ($limits['offset_y_min'] ?? -1000), -$characterY);
        $scale = max((float) ($limits['scale_min'] ?? .1), min((float) ($limits['scale_max'] ?? 50), (float) ($adjustments['scale'] ?? 1)));
        $offsetYMax = 1 - $characterY + (((float) ($character['max_height'] ?? .84) * $scale) / 2);

        return [
            'scale' => $scale,
            'offset_x' => max($offsetXMin, min($offsetXMax, (float) ($adjustments['offset_x'] ?? 0))),
            'offset_y' => max($offsetYMin, min($offsetYMax, (float) ($adjustments['offset_y'] ?? 0))),
        ];
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

    public function renderSolid(\GdImage $canvas, array $layer, array $variantOptions): void
    {
        $colour = $layer['colour'] ?? null;
        if ($option = $layer['variant_option'] ?? null) {
            $value = mb_strtolower(trim((string) ($variantOptions[$option] ?? '')));
            $colour = $layer['colours_by_variant'][$value] ?? $layer['fallback_colour'] ?? null;
        }
        imagefill($canvas, 0, 0, $this->colour($canvas, $colour ?? '#ffffff'));
    }

    public function renderTransparent(\GdImage $canvas): void
    {
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
    }

    /**
     * Generic raster background layer: draws a resolved `context_background` sibling asset
     * full-bleed (cover fit) beneath other layers, filling the template fallback colour when
     * none exists. Retained as a reusable layer type; the current wall-print templates
     * instead bake the scene into a single cover-fitted generation_asset.
     */
    public function renderContextBackground(\GdImage $canvas, ?\GdImage $background, array $config): void
    {
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);

        if (! $background instanceof \GdImage) {
            imagealphablending($canvas, false);
            imagefill($canvas, 0, 0, $this->colour($canvas, $config['fallback_colour'] ?? '#efe9e2'));
            imagesavealpha($canvas, true);
            imagealphablending($canvas, true);

            return;
        }

        $sourceWidth = imagesx($background);
        $sourceHeight = imagesy($background);
        // Cover fit: the larger scale so the background fills the whole canvas, then
        // centre-crop the overflow.
        $scale = max($canvasWidth / $sourceWidth, $canvasHeight / $sourceHeight);
        $drawWidth = max(1, (int) ceil($sourceWidth * $scale));
        $drawHeight = max(1, (int) ceil($sourceHeight * $scale));
        $destX = (int) round(($canvasWidth - $drawWidth) / 2);
        $destY = (int) round(($canvasHeight - $drawHeight) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $background, $destX, $destY, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);
    }

    public function renderTextPattern(\GdImage $canvas, array $layer, array $definition, array $personalisation, ProductDesignTemplate $template, array $variantOptions): void
    {
        $textValue = trim((string) ($personalisation[$layer['field'] ?? '']['value'] ?? ''));
        if ($textValue === '') {
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
        $colourValue = $layer['colour'] ?? null;
        if ($option = $layer['variant_option'] ?? null) {
            $variantValue = mb_strtolower(trim((string) ($variantOptions[$option] ?? '')));
            $colourValue = $layer['colours_by_variant'][$variantValue] ?? null;
            if (! is_string($colourValue)) {
                throw new RuntimeException("The design text colour is not configured for [{$option}:{$variantValue}].");
            }
        }
        $colour = $this->colour($canvas, $colourValue ?? '#ffffff');
        usort($items, fn (array $left, array $right): int => ((float) ($right['size'] ?? 0)) <=> ((float) ($left['size'] ?? 0)));
        $occupied = [];
        $collisionPadding = max(2, (int) round(min(imagesx($canvas), imagesy($canvas)) * .006));
        imagesetclip($canvas, $rect['x'], $rect['y'], $rect['x'] + $rect['width'], $rect['y'] + $rect['height']);
        foreach ($items as $item) {
            $style = $styles[$item['style'] ?? ''] ?? null;
            if (! is_array($style)) {
                throw new RuntimeException('The text pattern references an invalid style.');
            }
            $font = $this->fontPathFromStyle($template, $style);
            $rotation = (float) ($item['rotation'] ?? 0);
            if (! in_array($rotation, [0.0, 90.0], true)) {
                throw new RuntimeException('Design text rotation must be either 0 or 90 degrees.');
            }
            $letterSpacing = $style['letter_spacing'] ?? 0;
            if (! is_numeric($letterSpacing) || (float) $letterSpacing < 0 || (float) $letterSpacing > 1) {
                throw new RuntimeException('Design letter spacing must be between 0 and 1 em.');
            }
            $text = ($style['uppercase'] ?? false) ? mb_strtoupper($textValue) : $textValue;
            $centreX = $rect['x'] + ((float) ($item['x'] ?? 0) * $rect['width']);
            $centreY = $rect['y'] + ((float) ($item['y'] ?? 0) * $rect['height']);
            $fontSize = max(18, (int) round(min(imagesx($canvas), imagesy($canvas)) * (float) ($item['size'] ?? .04)));
            $placement = null;

            while ($fontSize >= 18) {
                $candidate = $this->textPlacement($fontSize, $rotation, $font, $text, (float) $letterSpacing, $centreX, $centreY);
                $visible = $this->intersectingRect($candidate['rect'], $rect);
                $overlaps = $visible === null || collect($occupied)->contains(
                    fn (array $used): bool => $this->rectanglesOverlap($visible, $used, $collisionPadding)
                );

                if (! $overlaps) {
                    $placement = $candidate;
                    $occupied[] = $visible;
                    break;
                }

                $fontSize = (int) floor($fontSize * .92);
            }

            if ($placement === null) {
                continue;
            }

            $this->drawText($canvas, $fontSize, $rotation, $placement['x'], $placement['y'], $colour, $font, $text, (float) $letterSpacing);
        }
        imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
    }

    /** @return array{x:int,y:int,rect:array{x:int,y:int,width:int,height:int}} */
    private function textPlacement(int $fontSize, float $rotation, string $font, string $text, float $letterSpacing, float $centreX, float $centreY): array
    {
        $bounds = imagettfbbox($fontSize, $rotation, $font, $text);
        if ($bounds === false) {
            throw new RuntimeException('The personalised text could not be measured.');
        }
        $minX = min($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
        $maxX = max($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
        $minY = min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
        $maxY = max($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
        $extra = $fontSize * $letterSpacing * max(0, mb_strlen($text) - 1);
        $width = ($maxX - $minX) + ($rotation === 0.0 ? $extra : 0);
        $height = ($maxY - $minY) + ($rotation === 90.0 ? $extra : 0);

        return [
            'x' => (int) round($centreX - (($maxX - $minX) / 2) - $minX),
            'y' => (int) round($centreY - (($maxY - $minY) / 2) - $minY),
            'rect' => [
                'x' => (int) floor($centreX - ($width / 2)),
                'y' => (int) floor($centreY - ($height / 2)),
                'width' => max(1, (int) ceil($width)),
                'height' => max(1, (int) ceil($height)),
            ],
        ];
    }

    /** @return array{x:int,y:int,width:int,height:int}|null */
    private function intersectingRect(array $candidate, array $bounds): ?array
    {
        $left = max($candidate['x'], $bounds['x']);
        $top = max($candidate['y'], $bounds['y']);
        $right = min($candidate['x'] + $candidate['width'], $bounds['x'] + $bounds['width']);
        $bottom = min($candidate['y'] + $candidate['height'], $bounds['y'] + $bounds['height']);

        return $right <= $left || $bottom <= $top
            ? null
            : ['x' => $left, 'y' => $top, 'width' => $right - $left, 'height' => $bottom - $top];
    }

    private function rectanglesOverlap(array $left, array $right, int $padding): bool
    {
        return $left['x'] < $right['x'] + $right['width'] + $padding
            && $right['x'] < $left['x'] + $left['width'] + $padding
            && $left['y'] < $right['y'] + $right['height'] + $padding
            && $right['y'] < $left['y'] + $left['height'] + $padding;
    }

    private function drawText(\GdImage $canvas, int $fontSize, float $rotation, int $x, int $y, int $colour, string $font, string $text, float $letterSpacing): void
    {
        if ($letterSpacing === 0.0 || mb_strlen($text) < 2) {
            imagettftext($canvas, $fontSize, $rotation, $x, $y, $colour, $font, $text);

            return;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new RuntimeException('The personalised text contains invalid Unicode.');
        }
        $spacing = $fontSize * $letterSpacing;
        $radians = deg2rad($rotation);
        $directionX = cos($radians);
        $directionY = -sin($radians);
        $extraWidth = $spacing * max(0, count($characters) - 1);
        $cursorX = $x - ($directionX * $extraWidth / 2);
        $cursorY = $y - ($directionY * $extraWidth / 2);

        foreach ($characters as $index => $character) {
            imagettftext($canvas, $fontSize, $rotation, (int) round($cursorX), (int) round($cursorY), $colour, $font, $character);
            $bounds = imagettfbbox($fontSize, 0, $font, $character);
            if ($bounds === false) {
                throw new RuntimeException('A personalised character could not be measured.');
            }
            $advance = max($bounds[0], $bounds[2], $bounds[4], $bounds[6]) - min($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
            if ($index < count($characters) - 1) {
                $advance += $spacing;
            }
            $cursorX += $directionX * $advance;
            $cursorY += $directionY * $advance;
        }
    }

    public function renderGenerationAsset(\GdImage $canvas, \GdImage $source, array $layer, array $config, array $adjustments = [], array $clipPolicy = ['mode' => 'canvas']): void
    {
        $fit = $layer['fit'] ?? null;
        if (! in_array($fit, ['contain', 'cover'], true)) {
            throw new RuntimeException('Generation artwork must use contain or cover fitting.');
        }
        $rect = $this->characterBox($config, imagesx($canvas), imagesy($canvas));
        $sourceRect = $this->opaqueBounds($source);
        if ($fit === 'cover') {
            // Fill the box (crop the overflow) — used by wall prints, where the artwork is a
            // single character-plus-background image that should fill the print edge to edge.
            $coverScale = max($rect['width'] / $sourceRect['width'], $rect['height'] / $sourceRect['height']);
            $base = ['width' => max(1, (int) round($sourceRect['width'] * $coverScale)), 'height' => max(1, (int) round($sourceRect['height'] * $coverScale))];
        } else {
            $base = $this->containedSize($sourceRect['width'], $sourceRect['height'], $rect['width'], $rect['height']);
        }
        $scale = max(.1, min(50, (float) ($adjustments['scale'] ?? 1)));
        $width = max(1, (int) round($base['width'] * $scale));
        $height = max(1, (int) round($base['height'] * $scale));
        $offsetX = max(-1000, min(1000, (float) ($adjustments['offset_x'] ?? 0))) * imagesx($canvas);
        $offsetY = max(-1000, min(1000, (float) ($adjustments['offset_y'] ?? 0))) * imagesy($canvas);
        $x = $rect['x'] + (int) round(($rect['width'] - $width) / 2 + $offsetX);
        $y = $rect['y'] + (int) round(($rect['height'] - $height) / 2 + $offsetY);

        $clip = $this->clipBox($clipPolicy, imagesx($canvas), imagesy($canvas));

        // Crop the resampling operation to the configured visible area. At high zoom levels
        // the virtual destination can be enormous, while only this small window is visible.
        $visibleLeft = max($clip['x'], $x);
        $visibleTop = max($clip['y'], $y);
        $visibleRight = min($clip['x'] + $clip['width'], $x + $width);
        $visibleBottom = min($clip['y'] + $clip['height'], $y + $height);
        if ($visibleRight <= $visibleLeft || $visibleBottom <= $visibleTop) {
            return;
        }

        $sourceLeft = $sourceRect['x'] + (($visibleLeft - $x) / $width * $sourceRect['width']);
        $sourceTop = $sourceRect['y'] + (($visibleTop - $y) / $height * $sourceRect['height']);
        $sourceRight = $sourceRect['x'] + (($visibleRight - $x) / $width * $sourceRect['width']);
        $sourceBottom = $sourceRect['y'] + (($visibleBottom - $y) / $height * $sourceRect['height']);
        $sourceX = max($sourceRect['x'], (int) floor($sourceLeft));
        $sourceY = max($sourceRect['y'], (int) floor($sourceTop));
        $sourceWidth = max(1, min($sourceRect['x'] + $sourceRect['width'], (int) ceil($sourceRight)) - $sourceX);
        $sourceHeight = max(1, min($sourceRect['y'] + $sourceRect['height'], (int) ceil($sourceBottom)) - $sourceY);

        imagesetclip($canvas, $clip['x'], $clip['y'], $clip['x'] + $clip['width'] - 1, $clip['y'] + $clip['height'] - 1);
        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $source,
            $visibleLeft,
            $visibleTop,
            $sourceX,
            $sourceY,
            $visibleRight - $visibleLeft,
            $visibleBottom - $visibleTop,
            $sourceWidth,
            $sourceHeight,
        );
        imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
    }

    /** @return array{x:int,y:int,width:int,height:int} */
    private function clipBox(array $clip, int $canvasWidth, int $canvasHeight): array
    {
        if (($clip['mode'] ?? 'canvas') === 'canvas') {
            return ['x' => 0, 'y' => 0, 'width' => $canvasWidth, 'height' => $canvasHeight];
        }

        $x = min($canvasWidth - 1, max(0, (int) round((float) $clip['x'] * $canvasWidth)));
        $y = min($canvasHeight - 1, max(0, (int) round((float) $clip['y'] * $canvasHeight)));

        return [
            'x' => $x,
            'y' => $y,
            'width' => max(1, min($canvasWidth - $x, (int) round((float) $clip['width'] * $canvasWidth))),
            'height' => max(1, min($canvasHeight - $y, (int) round((float) $clip['height'] * $canvasHeight))),
        ];
    }

    /** @return array{x:int,y:int,width:int,height:int} */
    private function opaqueBounds(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        return $maxX < $minX || $maxY < $minY
            ? ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]
            : ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1];
    }

    public function trimTransparentImage(string $bytes): string
    {
        // Wall-print sources are large opaque scenes (~11 MP composition_source); decoding one
        // plus a same-size truecolor buffer overflows a default web memory_limit, which 500s the
        // editor overlay request and leaves it blank. Budget memory from the source size first.
        $info = @getimagesizefromstring($bytes);
        if ($info !== false) {
            $this->ensureMemoryForCanvas((int) $info[0], (int) $info[1]);
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw new RuntimeException('The generation asset is unreadable.');
        }
        $bounds = $this->opaqueBounds($source);

        // The overlay is only ever shown inside the ~1200px editor canvas, so downscale large
        // sources — no visible loss, and it keeps the response light instead of shipping an
        // 11 MP PNG on every character move/zoom. Resample straight from the opaque region into
        // the final (downscaled) canvas, so no full-size intermediate buffer is held.
        $scale = min(1, self::PREVIEW_MAX_EDGE / max($bounds['width'], $bounds['height']));
        $width = max(1, (int) round($bounds['width'] * $scale));
        $height = max(1, (int) round($bounds['height'] * $scale));
        $trimmed = imagecreatetruecolor($width, $height);
        imagealphablending($trimmed, false);
        imagesavealpha($trimmed, true);
        imagefill($trimmed, 0, 0, imagecolorallocatealpha($trimmed, 0, 0, 0, 127));
        imagecopyresampled($trimmed, $source, 0, 0, $bounds['x'], $bounds['y'], $width, $height, $bounds['width'], $bounds['height']);
        imagedestroy($source);

        ob_start();
        imagepng($trimmed);
        $result = ob_get_clean();
        imagedestroy($trimmed);

        return $result;
    }

    public function previewBytes(\GdImage $canvas): string
    {
        $scale = min(1, self::PREVIEW_MAX_EDGE / max(imagesx($canvas), imagesy($canvas)));
        $width = max(1, (int) round(imagesx($canvas) * $scale));
        $height = max(1, (int) round(imagesy($canvas) * $scale));
        $preview = imagecreatetruecolor($width, $height);
        imagealphablending($preview, false);
        imagesavealpha($preview, true);
        imagefill($preview, 0, 0, imagecolorallocatealpha($preview, 0, 0, 0, 127));
        imagecopyresampled($preview, $canvas, 0, 0, 0, 0, $width, $height, imagesx($canvas), imagesy($canvas));
        ob_start();
        imagewebp($preview, null, 86);
        $bytes = ob_get_clean();
        imagedestroy($preview);

        return $bytes;
    }

    private function ensureMemoryForCanvas(int $width, int $height): void
    {
        $megapixels = ($width * $height) / 1_000_000;
        if ($megapixels <= 6) {
            return;
        }
        $current = ini_get('memory_limit');
        if ($current === false || trim((string) $current) === '-1') {
            return; // already unlimited
        }
        // The full-resolution render holds several full-canvas buffers at once (the canvas,
        // the loaded source, the PNG encode buffer, the editor canvas) plus the framework
        // baseline. Even an A4 wall print (~9.6 MP) exceeds a default 128 MB web limit, so
        // budget generously from the canvas size and only ever raise the limit, never lower
        // it. (For production robustness this render should ideally move to a queued job.)
        $budgetBytes = ((int) ceil($megapixels * 24) + 320) * 1_048_576;
        if ($this->bytesFromShorthand((string) $current) < $budgetBytes) {
            @ini_set('memory_limit', (int) ceil($budgetBytes / 1_048_576).'M');
        }
    }

    private function bytesFromShorthand(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1_073_741_824,
            'm' => $number * 1_048_576,
            'k' => $number * 1024,
            default => $number,
        };
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

    /** @return array<string,string> */
    public function resolvedTemplateFontPaths(ProductDesignTemplate $template): array
    {
        $pattern = collect($template->definition()['layers'])->firstWhere('type', 'personalisation_text_pattern');

        return collect($pattern['styles'] ?? [])->mapWithKeys(fn (array $style, string $key) => [
            $key => $this->fontPathFromStyle($template, $style),
        ])->all();
    }

    private function fontPathFromStyle(ProductDesignTemplate $template, array $style): string
    {
        if (! isset($style['font_source'])) {
            return $this->fontPath((string) ($style['font_family'] ?? 'serif'));
        }

        $family = (string) ($style['font_family'] ?? '');
        $source = str_replace('\\', '/', (string) $style['font_source']);
        $weight = $style['font_weight'] ?? null;
        $allowed = [
            'Anton' => ['source' => 'assets/fonts/Anton-Regular.ttf', 'weight' => 400],
            'Bodoni Moda' => ['source' => 'assets/fonts/BodoniModa_18pt-Regular.ttf', 'weight' => 400],
            'Fredoka' => ['source' => 'assets/fonts/Fredoka-SemiBold.ttf', 'weight' => 600],
            'Allura' => ['source' => 'assets/fonts/Allura-Regular.ttf', 'weight' => 400],
        ];
        if (! isset($allowed[$family]) || $source !== $allowed[$family]['source'] || $weight !== $allowed[$family]['weight']) {
            throw new RuntimeException("Unsupported design font declaration [{$family}].");
        }

        $templateRoot = realpath(resource_path('product-designs/'.dirname($template->definition_path)));
        $path = realpath(($templateRoot ?: '').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $source));
        if (! $templateRoot || ! $path || ! str_starts_with(strtolower($path), strtolower($templateRoot.DIRECTORY_SEPARATOR)) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'ttf') {
            throw new RuntimeException("Design font source [{$source}] is unavailable or unsafe.");
        }

        return $path;
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
