<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LunchboxProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
    }

    public function test_lunchbox_catalogue_data_is_complete_and_product_is_public(): void
    {
        $product = $this->lunchbox();
        $variants = $product->variants()->active()->ordered()->get();

        $this->assertTrue($product->is_active);
        $this->assertSame('small-plastic-lunchbox', $product->slug);
        $this->assertSame(1950, $product->base_price_minor);
        $this->assertSame(['Blue', 'Pink', 'White'], $variants->pluck('name')->all());
        $this->assertSame(['blue', 'pink', 'white'], $variants->pluck('options')->pluck('colour')->all());
        $this->assertSame([1950], $variants->pluck('price_minor')->unique()->values()->all());
        $this->assertSame('small-lunchbox-v1', $product->designTemplate->key);
        $this->assertSame(['school-lunch'], $product->categories->pluck('slug')->all());
        $this->assertSame('blue', $product->preview_configuration['default_variant_options']['colour']);
        $this->assertStringContainsString('BPA-free', $product->preview_configuration['specifications'][0]['value']);
        $this->assertSame('18 × 12.4 × 6 cm', $product->preview_configuration['specifications'][2]['value']);

        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Small Plastic Lunchbox');
        $this->get(route('products.index'))->assertOk()->assertSee('Small Plastic Lunchbox');
        $this->get(route('categories.show', 'school-lunch'))->assertOk()->assertSee('Small Plastic Lunchbox');
        $this->get(route('sitemap.xml'))->assertOk()->assertSee('/products/small-plastic-lunchbox');
    }

    public function test_only_confirmed_provider_skus_are_mapped(): void
    {
        $variants = $this->lunchbox()->variants()->active()->ordered()->get()->keyBy(fn (ProductVariant $variant) => $variant->options['colour']);

        $this->assertSame('LUNCHBOX-BLUE', $variants['blue']->fulfilmentMappings()->firstOrFail()->provider_sku);
        $this->assertSame('LUNCHBOX-PINK', $variants['pink']->fulfilmentMappings()->firstOrFail()->provider_sku);
        $this->assertFalse($variants['white']->fulfilmentMappings()->exists());

        foreach (['blue', 'pink'] as $colour) {
            $mapping = $variants[$colour]->fulfilmentMappings()->firstOrFail();
            $this->assertSame('treatpod', $mapping->provider);
            $this->assertSame(['width' => 165, 'height' => 102, 'unit' => 'mm'], $mapping->configuration['physical_print_area']);
            $this->assertSame(['width' => 1949, 'height' => 1205, 'dpi' => 300], $variants[$colour]->requiredPrintResolution());
            $this->assertSame(300, $mapping->configuration['print_areas']['default']['dpi']);
            $this->assertFalse($mapping->configuration['print_areas']['default']['supplier_template_validated']);
        }

        $this->expectException(DomainException::class);
        $variants['white']->requiredPrintResolution();
    }

    public function test_template_and_variant_aware_marketing_gallery_are_prepared(): void
    {
        $product = $this->lunchbox();
        $definition = $product->designTemplate->definition();
        $pattern = collect($definition['layers'])->firstWhere('type', 'personalisation_text_pattern');

        $this->assertSame('transparent', $definition['layers'][0]['type']);
        $this->assertSame('colour', $pattern['variant_option']);
        $this->assertSame(['blue' => '#3B82F6', 'pink' => '#EC4899', 'white' => '#6B7280'], $pattern['colours_by_variant']);
        $this->assertSame([0, 90], collect($pattern['items'])->pluck('rotation')->unique()->sort()->values()->all());
        $this->assertSame('generation_asset', collect($definition['layers'])->last()['type']);

        foreach ($product->variants as $variant) {
            $this->assertGreaterThanOrEqual(1, $product->images()->where('product_variant_id', $variant->id)->count());
        }
        $this->assertSame($product->variants->first()->id, $product->primaryImage()->product_variant_id);
        $this->assertDirectoryExists(resource_path('product-assets/cattie/small-plastic-lunchbox/blue'));
        $this->assertDirectoryExists(resource_path('product-assets/cattie/small-plastic-lunchbox/pink'));
        $this->assertDirectoryExists(resource_path('product-assets/cattie/small-plastic-lunchbox/white'));
        $this->assertFileExists(resource_path('product-assets/treatpod/LUNCHBOX-SMALL/lunchbox-blue.jpg'));
        Storage::disk('public')->assertExists('products/small-plastic-lunchbox/supplier/blue/lunchbox-blue.jpg');
        Storage::disk('public')->assertExists('products/small-plastic-lunchbox/supplier/pink/lunchbox-pink.jpg');
        Storage::disk('public')->assertExists('products/small-plastic-lunchbox/supplier/white/lunchbox-wht.jpg');
    }

    public function test_prepared_product_page_uses_generic_lunchbox_labels_specs_and_variant_gallery(): void
    {
        $product = $this->lunchbox();
        $product->update(['is_active' => true]);

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('Lunchbox colour')->assertSee('Product details')
            ->assertSee('Food-safe BPA-free plastic')->assertSee('18 × 12.4 × 6 cm')->assertSee('16.5 × 10.2 cm')
            ->assertSee('imageVisible(image)', false)
            ->assertSee('products/small-plastic-lunchbox/catalogue/blue/', false)
            ->assertSee('products/small-plastic-lunchbox/catalogue/pink/', false)
            ->assertSee('products/small-plastic-lunchbox/catalogue/white/', false)
            ->assertDontSee('Bottle colour');
    }

    public function test_renderer_uses_the_lunchbox_text_colour_for_every_variant(): void
    {
        $expected = ['blue' => [59, 130, 246], 'pink' => [236, 72, 153], 'white' => [107, 114, 128]];

        foreach ($expected as $colour => $rgb) {
            [$session, $asset] = $this->renderInputs($colour);
            $design = app(RenderComposedDesign::class)->handle($session, $asset);
            $image = imagecreatefrompng(Storage::disk('local')->path($design->storage_key));
            $this->assertTrue($this->containsColour($image, ...$rgb), "The {$colour} text colour was not rendered.");
            imagedestroy($image);
        }
    }

    public function test_variant_colour_does_not_replace_the_personalised_name(): void
    {
        [$miaSession, $miaAsset] = $this->renderInputs('pink', 'Mia');
        $miaDesign = app(RenderComposedDesign::class)->handle($miaSession, $miaAsset);

        [$alexSession, $alexAsset] = $this->renderInputs('pink', 'Alex');
        $alexDesign = app(RenderComposedDesign::class)->handle($alexSession, $alexAsset);

        $this->assertNotSame(
            hash('sha256', Storage::disk('local')->get($miaDesign->editor_background_storage_key)),
            hash('sha256', Storage::disk('local')->get($alexDesign->editor_background_storage_key)),
            'Different entered names must produce different backgrounds for the same colour variant.',
        );
    }

    private function lunchbox(): Product
    {
        return Product::query()->where('slug', 'small-plastic-lunchbox')->with(['variants', 'images', 'categories', 'designTemplate', 'artworkStyles'])->firstOrFail();
    }

    private function renderInputs(string $colour, string $name = 'Mia'): array
    {
        $product = $this->lunchbox();
        $variant = $product->variants->first(fn (ProductVariant $variant) => $variant->options['colour'] === $colour);
        if (! $variant->fulfilmentMappings()->exists()) {
            $variant->fulfilmentMappings()->create(['provider' => 'test', 'provider_sku' => 'TEST-WHITE', 'configuration' => ['print_areas' => ['default' => ['width' => 1949, 'height' => 1205]]], 'is_active' => true]);
        }
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $product->artworkStyles->first()->id, 'personalisation' => ['name' => $name]], 'lunchbox-'.$colour.'-'.mb_strtolower($name));
        $fixtureKey = $colour.'-'.mb_strtolower($name);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'source-'.$fixtureKey.'.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => hash('sha256', $fixtureKey)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $product->artworkStyles->first()->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        $key = 'generated/lunchbox-'.$fixtureKey.'.png';
        Storage::disk('local')->put($key, file_get_contents(database_path('seeders/assets/fake-artwork/fake-artwork-a.png')));
        $asset = $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/png']);
        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        return [$session->fresh(['product.designTemplate', 'variant']), $asset];
    }

    private function containsColour(\GdImage $image, int $red, int $green, int $blue): bool
    {
        for ($y = 0; $y < imagesy($image); $y += 2) {
            for ($x = 0; $x < imagesx($image); $x += 2) {
                $colour = imagecolorat($image, $x, $y);
                if ((($colour >> 16) & 255) === $red && (($colour >> 8) & 255) === $green && ($colour & 255) === $blue) {
                    return true;
                }
            }
        }

        return false;
    }
}
