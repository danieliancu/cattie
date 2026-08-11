<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\PersonalisationFieldType;
use App\Models\ArtworkStyle;
use App\Models\FulfilmentProductMapping;
use App\Models\Product;
use App\Models\ProductDesignTemplate;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_styles_images_and_personalisation_belong_to_the_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => 'Style', 'slug' => 'style', 'prompt_key' => 'style', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        $image = $product->images()->create(['disk' => 'public', 'storage_key' => 'demo/test.svg', 'alt_text' => 'Test image']);
        $field = $product->personalisationFields()->create(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true]);

        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($product->artworkStyles->first()->is($style));
        $this->assertTrue($image->product->is($product));
        $this->assertSame(PersonalisationFieldType::Text, $field->type);
        $this->assertTrue($field->is_required);
    }

    public function test_listing_price_uses_lowest_active_variant_then_base_price(): void
    {
        $product = Product::factory()->create(['base_price_minor' => 2599]);
        ProductVariant::factory()->for($product)->create(['price_minor' => 3495]);
        ProductVariant::factory()->for($product)->create(['price_minor' => 2995]);
        ProductVariant::factory()->for($product)->create(['price_minor' => 100, 'is_active' => false]);

        $this->assertSame(2995, $product->displayPriceMinor());
        $this->assertSame('£29.95', $product->formattedPrice());
        $this->assertSame('£25.99', Product::factory()->create(['base_price_minor' => 2599])->formattedPrice());
    }

    public function test_seeded_catalogue_is_curated_and_images_are_local(): void
    {
        Storage::fake('public');
        $this->seed(CatalogueSeeder::class);

        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseHas('products', ['slug' => 'best-friend-pet-portrait', 'currency' => 'GBP']);
        $this->assertSame(2, Product::query()->where('slug', 'childrens-storybook-wall-print')->firstOrFail()->artworkStyles()->count());
        Storage::disk('public')->assertExists('demo/catalogue/storybook-print.svg');
        Storage::disk('public')->assertExists('demo/catalogue/hero-transformation.png');

        $bottle = Product::query()->where('slug', 'cattie-water-bottle')->firstOrFail();
        $this->assertTrue($bottle->is_active);
        $this->assertSame(1650, $bottle->base_price_minor);
        $this->assertSame([1650], $bottle->variants()->pluck('price_minor')->unique()->values()->all());
        $this->assertSame(12, $bottle->variants()->count());
        $this->assertSame(12, FulfilmentProductMapping::query()->where('provider', 'prodigi')->count());
        $black = $bottle->variants()->where('sku', 'BOTTLE-650-BLACK')->firstOrFail();
        $this->assertSame(['color' => 'black', 'size' => '650ml / 22oz'], $black->fulfilmentMappings()->firstOrFail()->configuration['attributes']);

        $this->assertTrue($bottle->usesStaticMockupBoundary());
        $this->assertSame('products/cattie-water-bottle/mockup/blank.jpg', $bottle->preview_configuration['mockup_asset']['storage_key']);
        $this->assertSame(4, $bottle->images()->count());
        $this->assertSame(
            [
                'products/cattie-water-bottle/catalogue/bottle01.jpg',
                'products/cattie-water-bottle/catalogue/bottle02.jpg',
                'products/cattie-water-bottle/catalogue/close-up.jpg',
                'products/cattie-water-bottle/catalogue/lid.jpg',
            ],
            $bottle->images()->pluck('storage_key')->all(),
        );
        $this->assertSame('products/cattie-water-bottle/catalogue/bottle01.jpg', $bottle->primaryImage()->storage_key);
        $this->assertFalse($bottle->images()->where('storage_key', 'products/cattie-water-bottle/mockup/blank.jpg')->exists());
        Storage::disk('public')->assertExists('products/cattie-water-bottle/mockup/blank.jpg');
        foreach ($bottle->images as $image) {
            Storage::disk('public')->assertExists($image->storage_key);
            $this->assertNotNull($image->product_variant_id);
        }

        $unrelated = Product::query()->where('slug', 'childrens-storybook-wall-print')->firstOrFail();
        $this->assertFalse($unrelated->usesStaticMockupBoundary());
        $this->assertNull($unrelated->designTemplate);

        $this->assertSame('bottle-wrap-v1', $bottle->designTemplate->key);
        $this->assertSame(1, ProductDesignTemplate::query()->count());
        $definition = $bottle->designTemplate->definition();
        $this->assertSame('normalized', $definition['coordinate_system']);
        $this->assertSame('variant_print_area', $definition['output_size']['source']);
        $this->assertStringNotContainsString('650ML-WATER-BOTTLE', json_encode($definition, JSON_THROW_ON_ERROR));

        $expectedResolutions = [
            'black / translucent' => [2498, 1828],
            'black' => [2750, 2279],
            'grey' => [2750, 2279],
            'lime' => [2716, 2125],
            'mint green' => [2750, 2279],
            'navy' => [2750, 2279],
            'orange' => [2750, 2279],
            'pebble blue' => [2750, 2279],
            'red' => [2750, 2279],
            'silver' => [2750, 2279],
            'white / clear' => [2498, 1828],
            'white' => [2750, 2279],
        ];
        foreach ($bottle->variants as $variant) {
            [$width, $height] = $expectedResolutions[$variant->options['colour']];
            $this->assertSame(['width' => $width, 'height' => $height], $variant->requiredPrintResolution('prodigi'));
        }

        $white = $bottle->variants()->where('sku', 'BOTTLE-650-WHITE')->firstOrFail();
        $style = $bottle->artworkStyles()->firstOrFail();
        [$session] = app(StartArtworkSession::class)->handle($bottle, [
            'variant_id' => $white->id,
            'artwork_style_id' => $style->id,
            'personalisation' => ['name' => 'Mia'],
        ]);
        $this->assertTrue($session->product->is($bottle));

        $this->get('/products')->assertOk()->assertSee('products/cattie-water-bottle/catalogue/bottle01.jpg');
        $this->get('/products/cattie-water-bottle')->assertOk()
            ->assertSee('products/cattie-water-bottle/catalogue/bottle01.jpg')
            ->assertSee('products/cattie-water-bottle/catalogue/bottle02.jpg')
            ->assertDontSee('products/cattie-water-bottle/mockup/blank.jpg');

        $this->seed(CatalogueSeeder::class);
        $this->assertSame(12, FulfilmentProductMapping::query()->where('provider', 'prodigi')->count());
        $this->assertSame(4, $bottle->fresh()->images()->count());
    }

    public function test_supplier_assets_are_classified_without_a_create_directory(): void
    {
        $directory = resource_path('product-assets/prodigi/650ML-WATER-BOTTLE');
        $this->assertDirectoryDoesNotExist($directory.'/create');
        $this->assertSame(
            [
                'Prodigi-copper-water-bottle-blank.jpg',
                'Prodigi-copper-water-bottle-close-up.jpg',
                'Prodigi-copper-water-bottle-lid.jpg',
                'Prodigi-copper-water-bottle01.jpg',
                'Prodigi-copper-water-bottle02.jpg',
            ],
            collect(glob($directory.'/*'))->map('basename')->sort()->values()->all(),
        );

        $assets = config('product-assets.suppliers.prodigi.650ML-WATER-BOTTLE.assets');
        $this->assertSame('primary', $assets['product-01']['role']);
        $this->assertSame('gallery', $assets['product-02']['role']);
        $this->assertSame('detail', $assets['close-up']['role']);
        $this->assertSame('detail', $assets['lid']['role']);
        $this->assertSame('mockup_source', $assets['blank']['role']);
    }

    public function test_required_resolution_never_falls_back_when_mapping_data_is_missing(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->expectException(DomainException::class);
        $variant->requiredPrintResolution('prodigi');
    }
}
