<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StationeryPencilTinProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '256M');
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
    }

    public function test_catalogue_variants_mappings_and_categories_are_complete(): void
    {
        $product = $this->product();
        $variants = $product->variants()->active()->ordered()->get();

        $this->assertTrue($product->is_active);
        $this->assertSame('personalised-stationery-pencil-tin', $product->slug);
        $this->assertSame(1795, $product->base_price_minor);
        $this->assertSame(['Blue', 'Pink', 'Silver'], $variants->pluck('name')->all());
        $this->assertSame(['CATTIE-PENCIL-TIN-BLUE', 'CATTIE-PENCIL-TIN-PINK', 'CATTIE-PENCIL-TIN-SILVER'], $variants->pluck('sku')->all());
        $this->assertSame(['blue', 'pink', 'silver'], $variants->pluck('options')->pluck('colour')->all());
        $this->assertSame(['school-lunch', 'school-accessories'], $product->categories->pluck('slug')->all());
        $this->assertSame('stationery-pencil-tin-v1', $product->designTemplate->key);
        $this->assertSame('blue', $product->preview_configuration['default_variant_options']['colour']);

        $expectedSkus = ['blue' => 'SUBSTATIONERYTIN-BLU', 'pink' => 'SUBSTATIONERYTIN-PNK', 'silver' => 'SUBSTATIONERYTIN'];
        foreach ($variants as $variant) {
            $mapping = $variant->fulfilmentMappings()->where('is_active', true)->firstOrFail();
            $this->assertSame('treatpod', $mapping->provider);
            $this->assertSame($expectedSkus[$variant->options['colour']], $mapping->provider_sku);
            $this->assertSame(['width' => 188, 'depth' => 80, 'height' => 24, 'unit' => 'mm'], $mapping->configuration['physical_product_dimensions']);
            $this->assertSame(['width' => 185, 'height' => 76, 'unit' => 'mm'], $mapping->configuration['physical_print_area']);
            $this->assertSame(['width' => 2185, 'height' => 898], $variant->requiredPrintResolution());
            $this->assertSame(300, $mapping->configuration['print_areas']['default']['dpi']);
            $this->assertFalse($mapping->configuration['print_areas']['default']['supplier_template_validated']);
        }
    }

    public function test_template_uses_white_insert_and_variant_defined_name_colours(): void
    {
        $definition = $this->product()->designTemplate->definition();
        $pattern = collect($definition['layers'])->firstWhere('type', 'personalisation_text_pattern');

        $this->assertSame(['id' => 'background', 'type' => 'solid', 'colour' => '#ffffff'], $definition['layers'][0]);
        $this->assertSame('name', $pattern['field']);
        $this->assertSame('colour', $pattern['variant_option']);
        $this->assertSame(['blue' => '#2563EB', 'pink' => '#EC4899', 'silver' => '#6B7280'], $pattern['colours_by_variant']);
        $this->assertSame([0, 90], collect($pattern['items'])->pluck('rotation')->unique()->sort()->values()->all());
        $this->assertSame(['caps-condensed', 'serif-display', 'rounded-display', 'script-display'], array_keys($pattern['styles']));
        $this->assertSame('generation_asset', collect($definition['layers'])->last()['type']);
    }

    public function test_marketing_gallery_is_variant_aware_and_blue_is_primary(): void
    {
        $product = $this->product();

        foreach (['blue', 'pink', 'silver'] as $colour) {
            $variant = $product->variants->first(fn (ProductVariant $variant) => $variant->options['colour'] === $colour);
            $images = $product->images()->where('product_variant_id', $variant->id)->get();
            $this->assertGreaterThanOrEqual(3, $images->count());
            $this->assertTrue($images->every(fn ($image) => str_contains($image->storage_key, "/{$colour}/")));
        }
        $this->assertSame('blue', $product->primaryImage()->variant->options['colour']);
        $this->assertStringContainsString('/catalogue/blue/', $product->primaryImage()->storage_key);

        foreach (config('product-assets.suppliers.treatpod.STATIONERY-PENCIL-TIN.assets') as $asset) {
            $this->assertFileExists(resource_path('product-assets/treatpod/STATIONERY-PENCIL-TIN/'.$asset['filename']));
            Storage::disk('public')->assertExists($asset['public']['storage_key']);
            $this->assertFalse($product->images()->where('storage_key', $asset['public']['storage_key'])->exists());
        }
    }

    public function test_product_page_workspace_and_public_catalogue_are_generic(): void
    {
        $product = $this->product();

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('Personalised Stationery &amp; Pencil Tin', false)
            ->assertSee('Tin colour')->assertSee('18.8 Ã— 8 Ã— 2.4 cm')->assertSee('18.5 Ã— 7.6 cm')
            ->assertSee('products/personalised-stationery-pencil-tin/catalogue/blue/', false)
            ->assertSee('products/personalised-stationery-pencil-tin/catalogue/pink/', false)
            ->assertSee('products/personalised-stationery-pencil-tin/catalogue/silver/', false)
            ->assertDontSee('TreatPod')->assertDontSee('SUBSTATIONERYTIN');
        $this->get(route('products.index'))->assertOk()->assertSee('Personalised Stationery &amp; Pencil Tin', false);
        $this->get(route('categories.show', 'school-accessories'))->assertOk()->assertSee('Personalised Stationery &amp; Pencil Tin', false);
        $this->get(route('categories.show', 'school-lunch'))->assertOk()->assertSee('Personalised Stationery &amp; Pencil Tin', false);
        $this->get(route('sitemap.xml'))->assertOk()->assertSee('/products/personalised-stationery-pencil-tin');
    }

    public function test_renderer_outputs_exact_canvas_white_insert_and_name_colour(): void
    {
        $expected = ['blue' => [37, 99, 235], 'pink' => [236, 72, 153], 'silver' => [107, 114, 128]];

        foreach ($expected as $colour => $rgb) {
            [$session, $asset] = $this->renderInputs($colour);
            $design = app(RenderComposedDesign::class)->handle($session, $asset);
            $image = imagecreatefrompng(Storage::disk('local')->path($design->storage_key));
            $this->assertSame([2185, 898], [imagesx($image), imagesy($image)]);
            $this->assertSame([255, 255, 255], $this->rgbAt($image, 0, 0));
            $this->assertTrue($this->containsColour($image, ...$rgb), "The {$colour} name colour was not rendered.");
            imagedestroy($image);
        }
    }

    public function test_seeding_is_idempotent_and_workspace_left_column_is_sticky_on_desktop(): void
    {
        $product = $this->product();
        $variantIds = $product->variants->pluck('id')->all();
        $imageKeys = $product->images->pluck('storage_key')->all();
        $this->seed(CatalogueSeeder::class);
        $product->refresh()->load(['variants', 'images']);
        $this->assertSame($variantIds, $product->variants->pluck('id')->all());
        $this->assertSame($imageKeys, $product->images->pluck('storage_key')->all());

        [$session, $asset] = $this->renderInputs('blue');
        app(RenderComposedDesign::class)->handle($session, $asset);
        $this->withCookie('cattie_guest_token', 'pencil-tin-blue')
            ->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('class="lg:sticky lg:top-24 lg:self-start"', false);
    }

    private function product(): Product
    {
        return Product::query()->where('slug', 'personalised-stationery-pencil-tin')
            ->with(['variants.fulfilmentMappings', 'images.variant', 'categories', 'designTemplate', 'artworkStyles'])->firstOrFail();
    }

    private function renderInputs(string $colour): array
    {
        $product = $this->product();
        $variant = $product->variants->first(fn (ProductVariant $variant) => $variant->options['colour'] === $colour);
        [$session] = app(StartArtworkSession::class)->handle($product, [
            'variant_id' => $variant->id,
            'artwork_style_id' => $product->artworkStyles->first()->id,
            'personalisation' => ['name' => 'Mia'],
        ], 'pencil-tin-'.$colour);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'pencil-source-'.$colour.'.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => hash('sha256', $colour)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $product->artworkStyles->first()->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        $key = 'generated/pencil-tin-'.$colour.'.png';
        Storage::disk('local')->put($key, file_get_contents(database_path('seeders/assets/fake-artwork/fake-artwork-a.png')));
        $asset = $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/png']);
        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        return [$session->fresh(['product.designTemplate', 'variant']), $asset];
    }

    private function rgbAt(\GdImage $image, int $x, int $y): array
    {
        $colour = imagecolorat($image, $x, $y);

        return [($colour >> 16) & 255, ($colour >> 8) & 255, $colour & 255];
    }

    private function containsColour(\GdImage $image, int $red, int $green, int $blue): bool
    {
        for ($y = 0; $y < imagesy($image); $y += 2) {
            for ($x = 0; $x < imagesx($image); $x += 2) {
                if ($this->rgbAt($image, $x, $y) === [$red, $green, $blue]) {
                    return true;
                }
            }
        }

        return false;
    }
}
