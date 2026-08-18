<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ComposedDesign;
use App\Models\Product;
use App\Models\ProductDesignTemplate;
use App\Services\CharacterUpscaleProcessor;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WallPrintCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '512M');
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CatalogueSeeder::class);
    }

    /** Print geometry = real TreatPod trim + 6.5 mm bleed each side, at 300 DPI. */
    public function test_print_resolution_is_spec_driven_at_300_dpi_with_bleed(): void
    {
        $expected = [
            'custom-a4-wall-print' => [2634, 3661],
            'custom-a3-wall-print' => [3661, 5114],
            'custom-a2-wall-print' => [5114, 7169],
            'custom-landscape-a4-wall-print' => [3661, 2634],
            'custom-landscape-a3-wall-print' => [5114, 3661],
            'custom-landscape-a2-wall-print' => [7169, 5114],
        ];

        foreach ($expected as $slug => [$width, $height]) {
            $product = Product::query()->where('slug', $slug)->with('variants.fulfilmentMappings')->firstOrFail();
            foreach ($product->variants as $variant) {
                $this->assertSame(['width' => $width, 'height' => $height, 'dpi' => 300], $variant->requiredPrintResolution('default'), $slug);
                $physical = $variant->fulfilmentMappings->firstWhere('provider', 'treatpod')->configuration['physical_print_area'];
                $this->assertSame(6.5, $physical['bleed_mm']);
                $this->assertArrayHasKey('trim_width_mm', $physical);
            }
        }
        $this->assertNotSame($expected['custom-a4-wall-print'], $expected['custom-a3-wall-print']);
    }

    public function test_all_six_wall_prints_map_to_the_orientation_template(): void
    {
        foreach (['custom-a4-wall-print', 'custom-a3-wall-print', 'custom-a2-wall-print'] as $slug) {
            $this->assertSame('wall-print-v1-portrait', Product::query()->where('slug', $slug)->firstOrFail()->designTemplate?->key, $slug);
        }
        foreach (['custom-landscape-a4-wall-print', 'custom-landscape-a3-wall-print', 'custom-landscape-a2-wall-print'] as $slug) {
            $this->assertSame('wall-print-v1-landscape', Product::query()->where('slug', $slug)->firstOrFail()->designTemplate?->key, $slug);
        }
    }

    /** The wall-print composition is ONE cover-fitted image (subject + background together). */
    public function test_wall_print_template_is_a_single_cover_fitted_image(): void
    {
        $definition = ProductDesignTemplate::query()->where('key', 'wall-print-v1-portrait')->firstOrFail()->definition();
        $this->assertCount(1, $definition['layers']);
        $this->assertSame('generation_asset', $definition['layers'][0]['type']);
        $this->assertSame('cover', $definition['layers'][0]['fit']);
        // The whole image is the resizable unit (box = full canvas).
        $this->assertSame(1.0, $definition['character']['max_width']);
        $this->assertSame(1.0, $definition['character']['max_height']);
    }

    public function test_wall_print_uses_the_design_product_tabs_not_the_ready_fallback(): void
    {
        foreach (['custom-a4-wall-print', 'custom-landscape-a4-wall-print'] as $slug) {
            [$session, $artwork] = $this->wallPrintSession($slug, 'flow-'.$slug);
            app(RenderComposedDesign::class)->handle($session, $artwork);

            $this->withCookie('cattie_guest_token', 'flow-'.$slug)->get(route('products.show', $slug))
                ->assertOk()
                ->assertSee('>Design</button>', false)
                ->assertSee('>Product</button>', false)
                ->assertDontSee('Your artwork is ready.')
                ->assertSee('frameOverlayUrl', false)
                // Frame selector like the bottle: "Black" with the price beneath.
                ->assertSee('<strong class="block">Black</strong>', false)
                ->assertDontSee('Black Frame')
                // Name disappears completely for wall prints.
                ->assertDontSee('id="artwork-name"', false);
        }
    }

    /** The single image is cover-fitted, so the print fills edge to edge (opaque corners). */
    public function test_artwork_cover_fills_the_print_edge_to_edge(): void
    {
        [$session, $artwork] = $this->wallPrintSession('custom-a4-wall-print', 'cover-owner');
        $design = app(RenderComposedDesign::class)->handle($session, $artwork);

        $this->assertSame([2634, 3661], [$design->width, $design->height]);
        $print = imagecreatefromstring(Storage::disk('local')->get($design->storage_key));
        // Cover fill: an opaque image fills the whole canvas, including the corners.
        $this->assertLessThan(20, (imagecolorat($print, 4, 4) >> 24) & 0x7F);
        $this->assertLessThan(20, (imagecolorat($print, 2630, 3657) >> 24) & 0x7F);
        imagedestroy($print);
    }

    /** Frame change swaps only the overlay (in the Design tab); it never re-renders the artwork. */
    public function test_frame_switch_reuses_the_design_and_returns_the_overlay(): void
    {
        [$session, $artwork] = $this->wallPrintSession('custom-a4-wall-print', 'frame-owner');
        $design = app(RenderComposedDesign::class)->handle($session, $artwork);
        $count = $session->composedDesigns()->count();

        $white = $session->product->variants->firstWhere(fn ($v) => ($v->options['frame'] ?? '') === 'white');
        $response = $this->withCookie('cattie_guest_token', 'frame-owner')->post(route('artwork.variant', $session->public_id), [
            'variant_id' => $white->id,
            'design_id' => $design->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('design_id', $design->id);
        $this->assertStringContainsString('wall-print-frame-portrait-white.svg', $response->json('frame_overlay_url'));
        $this->assertSame($count, $session->composedDesigns()->count());
    }

    public function test_wall_print_resize_is_effectively_unlimited(): void
    {
        foreach (['wall-print-v1-portrait', 'wall-print-v1-landscape'] as $key) {
            $limits = ProductDesignTemplate::query()->where('key', $key)->firstOrFail()->definition()['character']['adjustment_limits'];
            $this->assertGreaterThanOrEqual(5, $limits['scale_max']);
            $this->assertLessThanOrEqual(0.2, $limits['scale_min']);
        }
    }

    public function test_character_upscaler_raises_small_images_and_leaves_large_ones(): void
    {
        $processor = app(CharacterUpscaleProcessor::class);

        $small = $this->artworkPng(400, 600);
        $upscaled = $processor->process($small, 1600);
        [$width, $height] = getimagesizefromstring($upscaled);
        $this->assertGreaterThanOrEqual(1600, max($width, $height));

        // Already large enough → returned unchanged.
        $this->assertSame($upscaled, $processor->process($upscaled, 1600));
    }

    /** The editor character overlay (artwork.assets?trim=1) must render, not 500 on a big opaque scene. */
    public function test_editor_character_overlay_is_served_downscaled_without_exhausting_memory(): void
    {
        [$session, $artwork] = $this->wallPrintSession('custom-a3-wall-print', 'overlay-owner');
        // Swap in a large opaque scene like the real ~11 MP composition_source: a full-size decode
        // plus a same-size truecolor buffer would overflow a default web memory_limit and blank the overlay.
        $bigKey = 'artwork/generated/'.uniqid().'-big-composition.png';
        Storage::disk('local')->put($bigKey, $this->artworkPng(1600, 2400));
        $artwork->update(['storage_key' => $bigKey, 'width' => 1600, 'height' => 2400]);

        $response = $this->withCookie('cattie_guest_token', 'overlay-owner')
            ->get(route('artwork.assets', [$session->public_id, $artwork, 'trim' => 1]));

        $response->assertOk();
        $size = getimagesizefromstring($response->getContent());
        $this->assertNotFalse($size);
        $this->assertSame('image/png', $size['mime']);
        $this->assertLessThanOrEqual(1200, max($size[0], $size[1]), 'The editor overlay must be downscaled to the editor canvas size.');
    }

    private function wallPrintSession(string $slug, string $token): array
    {
        $product = Product::query()->where('slug', $slug)->with(['variants', 'artworkStyles'])->firstOrFail();
        $variant = $product->variants->firstWhere(fn ($v) => ($v->options['frame'] ?? '') === 'black');
        $style = $product->artworkStyles->first();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => []], $token);

        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'source-'.uniqid().'.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => hash('sha256', uniqid())]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'quality' => 'standard', 'output_size' => '1024x1536', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);

        // The wall-print artwork is a single opaque image: subject + background together.
        $key = 'artwork/generated/'.uniqid().'-composition.png';
        Storage::disk('local')->put($key, $this->artworkPng(1024, 1536));
        $artwork = $generation->assets()->create(['kind' => 'composition_source', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/png', 'width' => 1024, 'height' => 1536]);

        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        return [$session->fresh(['product.variants', 'variant', 'currentGeneration.assets']), $artwork];
    }

    /** An opaque "scene" image (subject blob over a graded background) — no transparency. */
    private function artworkPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, 150, 170 + ($y % 40), 200 - ($y % 50)));
        }
        imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.55), (int) ($width * 0.5), (int) ($height * 0.6), imagecolorallocate($image, 220, 130, 90));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
