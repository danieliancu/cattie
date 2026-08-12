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

        $this->assertDatabaseCount('products', 9);
        $this->assertDatabaseHas('products', ['slug' => 'best-friend-pet-portrait', 'currency' => 'GBP']);
        $this->assertSame(2, Product::query()->where('slug', 'childrens-storybook-wall-print')->firstOrFail()->artworkStyles()->count());
        Storage::disk('public')->assertExists('demo/catalogue/storybook-print.svg');
        Storage::disk('public')->assertExists('demo/catalogue/hero-transformation.png');

        $bottle = Product::query()->where('slug', 'cattie-water-bottle')->firstOrFail();
        $this->assertFalse($bottle->is_active);
        $this->assertSame(1650, $bottle->base_price_minor);
        $this->assertSame([1650], $bottle->variants()->pluck('price_minor')->unique()->values()->all());
        $this->assertSame(4, $bottle->variants()->count());
        $this->assertSame(4, FulfilmentProductMapping::query()->where('provider', 'prodigi')->count());
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
        }
        $this->assertSame(['black', 'grey', 'navy', 'red'], $bottle->variants()->orderBy('sort_order')->get()->pluck('options')->pluck('colour')->all());

        $unrelated = Product::query()->where('slug', 'childrens-storybook-wall-print')->firstOrFail();
        $this->assertFalse($unrelated->usesStaticMockupBoundary());
        $this->assertNull($unrelated->designTemplate);

        $this->assertSame('bottle-wrap-v1', $bottle->designTemplate->key);
        $this->assertSame(4, $bottle->designTemplate->version);
        $this->assertSame(12, $bottle->personalisationFields()->where('key', 'name')->firstOrFail()->validation_rules['max']);
        $this->assertSame(4, ProductDesignTemplate::query()->count());
        $definition = $bottle->designTemplate->definition();
        $this->assertSame('normalized', $definition['coordinate_system']);
        $this->assertSame('variant_print_area', $definition['output_size']['source']);
        $this->assertSame('assets/fonts/Anton-Regular.ttf', $definition['layers'][1]['styles']['caps-condensed']['font_source']);
        $this->assertSame(600, $definition['layers'][1]['styles']['rounded-display']['font_weight']);
        $this->assertStringNotContainsString('650ML-WATER-BOTTLE', json_encode($definition, JSON_THROW_ON_ERROR));

        $expectedResolutions = [
            'black' => [2750, 2279],
            'grey' => [2750, 2279],
            'navy' => [2750, 2279],
            'red' => [2750, 2279],
        ];
        foreach ($bottle->variants as $variant) {
            [$width, $height] = $expectedResolutions[$variant->options['colour']];
            $this->assertSame(['width' => $width, 'height' => $height], $variant->requiredPrintResolution());
        }

        $treatPodBottle = Product::query()->where('slug', 'water-bottle-with-red-flip-lid')->firstOrFail();
        $this->assertTrue($treatPodBottle->is_active);
        $this->assertSame('Water Bottle with Red Flip Lid', $treatPodBottle->name);
        $this->assertSame(['white', 'silver'], $treatPodBottle->variants()->active()->ordered()->get()->pluck('options')->pluck('colour')->all());
        $this->assertSame(['CATTIE-WB-750-WHITE', 'CATTIE-WB-750-SILVER'], $treatPodBottle->variants()->active()->ordered()->pluck('sku')->all());
        $this->assertSame(['WB-750MLWHT-FLIPRED', 'WB-750MLSLV-FLIPRED'], $treatPodBottle->variants()->active()->ordered()->get()->map(fn ($variant) => $variant->fulfilmentMappings()->firstOrFail()->provider_sku)->all());
        foreach ($treatPodBottle->variants()->active()->get() as $variant) {
            $mapping = $variant->fulfilmentMappings()->firstOrFail();
            $this->assertSame('treatpod', $mapping->provider);
            $this->assertSame(['width' => 230, 'height' => 170, 'unit' => 'mm'], $mapping->configuration['physical_print_area']);
            $this->assertSame(['width' => 2717, 'height' => 2008], $variant->requiredPrintResolution());
            $this->assertFalse($mapping->configuration['print_areas']['default']['supplier_template_validated']);
        }
        $newDefinition = $treatPodBottle->designTemplate->definition();
        $this->assertSame('red-flip-bottle-wrap-v1', $newDefinition['key']);
        $this->assertSame('transparent', $newDefinition['layers'][0]['type']);
        $this->assertSame('#D62828', $newDefinition['layers'][1]['colour']);
        $this->assertSame(['white' => '#ffffff', 'silver' => '#d9dde2'], $treatPodBottle->preview_configuration['design_surfaces_by_variant']);

        $white = $treatPodBottle->variants()->where('sku', 'CATTIE-WB-750-WHITE')->firstOrFail();
        $style = $treatPodBottle->artworkStyles()->firstOrFail();
        [$session] = app(StartArtworkSession::class)->handle($treatPodBottle, [
            'variant_id' => $white->id,
            'artwork_style_id' => $style->id,
            'personalisation' => ['name' => 'Mia'],
        ]);
        $this->assertTrue($session->product->is($treatPodBottle));

        $this->assertSame('products/water-bottle-with-red-flip-lid/catalogue/white/anna-product.png', $treatPodBottle->primaryImage()->storage_key);
        $this->assertSame(4, $treatPodBottle->images()->where('product_variant_id', $white->id)->count());
        $silver = $treatPodBottle->variants()->where('sku', 'CATTIE-WB-750-SILVER')->firstOrFail();
        $this->assertSame(4, $treatPodBottle->images()->where('product_variant_id', $silver->id)->count());
        $this->get('/products')->assertOk()->assertSee('products/water-bottle-with-red-flip-lid/catalogue/white/anna-product.png')->assertDontSee('products/cattie-water-bottle/catalogue/bottle01.jpg');
        $this->get('/products/cattie-water-bottle')->assertNotFound();
        $this->get('/products/water-bottle-with-red-flip-lid')->assertOk()
            ->assertSee('Product examples')
            ->assertSee('Personalised examples shown for inspiration.')
            ->assertDontSee('Prodigi')->assertDontSee('650 ml')
            ->assertSee('products/water-bottle-with-red-flip-lid/catalogue/white/anna-product.png')
            ->assertSee('products/water-bottle-with-red-flip-lid/catalogue/silver/adrian-product.png')
            ->assertSee('imageVisible(image)', false);

        $this->seed(CatalogueSeeder::class);
        $this->assertSame(4, FulfilmentProductMapping::query()->where('provider', 'prodigi')->count());
        $this->assertSame(4, $bottle->fresh()->images()->count());
        $this->assertSame(7, FulfilmentProductMapping::query()->where('provider', 'treatpod')->count());
        $this->assertSame(8, $treatPodBottle->fresh()->images()->count());
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

        $treatPodDirectory = resource_path('product-assets/treatpod/WB-750ML-FLIP');
        $this->assertSame(
            [
                'wb-750mlslv-flipred-1a0f7e95-6a87-46c6-9c05-02ef967ee1ac.jpg',
                'wb-750mlslv-flipred.jpg',
                'wb-750mlslv-flipredm.jpg',
                'wb-750mlwht-flipred.jpg',
                'wb-750mlwht-flipredm.jpg',
            ],
            collect(glob($treatPodDirectory.'/*'))->map('basename')->sort()->values()->all(),
        );
        $treatPodAssets = config('product-assets.suppliers.treatpod.WB-750ML-FLIP.assets');
        $this->assertSame('wb-750mlwht-flipred.jpg', $treatPodAssets['white-primary']['filename']);
        $this->assertSame('primary', $treatPodAssets['white-primary']['role']);
        $this->assertSame('gallery', $treatPodAssets['white-gallery']['role']);
        $this->assertSame('wb-750mlslv-flipred.jpg', $treatPodAssets['silver-primary']['filename']);
        $this->assertSame('gallery', $treatPodAssets['silver-gallery']['role']);
        $this->assertSame('detail', $treatPodAssets['silver-detail']['role']);
    }

    public function test_required_resolution_never_falls_back_when_mapping_data_is_missing(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->expectException(DomainException::class);
        $variant->requiredPrintResolution();
    }

    public function test_multiple_active_mappings_are_rejected_as_ambiguous(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->fulfilmentMappings()->create(['provider' => 'one', 'provider_sku' => 'ONE', 'configuration' => ['print_areas' => ['default' => ['width' => 10, 'height' => 10]]], 'is_active' => true]);
        $variant->fulfilmentMappings()->create(['provider' => 'two', 'provider_sku' => 'TWO', 'configuration' => ['print_areas' => ['default' => ['width' => 20, 'height' => 20]]], 'is_active' => true]);

        $this->expectException(DomainException::class);
        $variant->requiredPrintResolution();
    }
}
