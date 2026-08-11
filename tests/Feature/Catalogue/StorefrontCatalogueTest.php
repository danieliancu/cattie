<?php

namespace Tests\Feature\Catalogue;

use App\Models\ArtworkStyle;
use App\Models\FulfilmentProductMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_catalogue_render_active_products_in_display_order(): void
    {
        Product::factory()->create(['name' => 'Second Gift', 'slug' => 'second', 'sort_order' => 2]);
        Product::factory()->create(['name' => 'First Gift', 'slug' => 'first', 'sort_order' => 1]);
        Product::factory()->create(['name' => 'Hidden Gift', 'slug' => 'hidden', 'is_active' => false]);

        $this->get(route('home'))->assertOk()->assertSee('Turn their favourite photo');
        $response = $this->get(route('products.index'))->assertOk()->assertSee('First Gift')->assertSee('Second Gift')->assertDontSee('Hidden Gift');
        $this->assertLessThan(strpos($response->getContent(), 'Second Gift'), strpos($response->getContent(), 'First Gift'));
    }

    public function test_product_routes_by_slug_and_inactive_product_returns_not_found(): void
    {
        $active = Product::factory()->create(['slug' => 'a-lovely-gift']);
        $inactive = Product::factory()->create(['slug' => 'not-for-sale', 'is_active' => false]);

        $this->get(route('products.show', $active->slug))->assertOk()->assertSee($active->name);
        $this->get(route('products.show', $inactive->slug))->assertNotFound();
    }

    public function test_product_page_shows_only_active_customer_options_and_never_supplier_data(): void
    {
        $product = Product::factory()->create(['name' => 'Portrait Print', 'slug' => 'portrait-print']);
        $visible = ProductVariant::factory()->for($product)->create(['name' => 'A3', 'sku' => 'INTERNAL-A3', 'price_minor' => 3495]);
        ProductVariant::factory()->for($product)->create(['name' => 'Discontinued', 'is_active' => false]);
        FulfilmentProductMapping::query()->create(['product_variant_id' => $visible->id, 'provider' => 'secret-provider', 'provider_sku' => 'PROVIDER-987', 'is_active' => true]);

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('A3')->assertSee('£34.95')->assertDontSee('Discontinued')
            ->assertDontSee('INTERNAL-A3')->assertDontSee('secret-provider')->assertDontSee('PROVIDER-987');
    }

    public function test_recommended_style_must_be_supported_or_page_falls_back(): void
    {
        $product = Product::factory()->create(['slug' => 'styled-gift']);
        $supported = ArtworkStyle::query()->create(['name' => 'Supported', 'slug' => 'supported', 'prompt_key' => 'supported', 'is_active' => true]);
        $unsupported = ArtworkStyle::query()->create(['name' => 'Unsupported', 'slug' => 'unsupported', 'prompt_key' => 'unsupported', 'is_active' => true]);
        $product->artworkStyles()->attach($supported);
        $product->update(['recommended_artwork_style_id' => $unsupported->id]);

        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Supported')->assertSee('Recommended')->assertDontSee('Unsupported');
    }
}
