<?php

namespace App\Domain\Artwork\Actions;

use App\Models\DesignTemplateVersion;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TestRenderDesignTemplateVersion
{
    public function __construct(private ValidateDesignTemplateConfiguration $validate, private RenderComposedDesign $renderer) {}

    public function handle(DesignTemplateVersion $version, ProductVariant $variant, string $sourceDisk, string $sourceKey, array $personalisation): DesignTemplateVersion
    {
        try {
            $definition = $this->validate->handle($version->configuration);
            $size = $variant->requiredPrintResolution($definition['output_size']['print_area'] ?? 'default');
            $source = @imagecreatefromstring(Storage::disk($sourceDisk)->get($sourceKey));
            if (! $source) {
                throw new RuntimeException('Test character image is unreadable.');
            }
            $canvas = imagecreatetruecolor($size['width'], $size['height']);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagealphablending($canvas, true);
            $values = collect($personalisation)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->keyBy('key')->all();
            foreach ($definition['layers'] as $layer) {
                match ($layer['type']) {
                    'transparent' => $this->renderer->renderTransparent($canvas), 'solid' => $this->renderer->renderSolid($canvas, $layer, $variant->options ?? []),
                    'personalisation_text_pattern' => $this->renderer->renderTextPattern($canvas, $layer, $definition, $values, $version->template, $variant->options ?? []),
                    'generation_asset' => $this->renderer->renderGenerationAsset($canvas, $source, $layer, $definition[$layer['config'] ?? ''] ?? []),
                    default => throw new RuntimeException('Unsupported layer.'),
                };
            }
            $bytes = $this->renderer->previewBytes($canvas);
            imagedestroy($source);
            imagedestroy($canvas);
            $key = "admin/template-previews/{$version->id}/".bin2hex(random_bytes(12)).'.webp';
            Storage::disk('local')->put($key, $bytes);
            $version->update(['last_test_render_at' => now(), 'last_test_render_status' => 'succeeded', 'last_test_render_error' => null, 'test_render_disk' => 'local', 'test_render_storage_key' => $key]);
        } catch (Throwable $e) {
            $version->update(['last_test_render_at' => now(), 'last_test_render_status' => 'failed', 'last_test_render_error' => $e->getMessage()]);
            throw $e;
        }

        return $version->refresh();
    }
}
