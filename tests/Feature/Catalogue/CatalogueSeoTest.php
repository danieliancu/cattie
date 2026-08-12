<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_metadata_canonical_breadcrumbs_and_pagination_are_correct(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'School & Lunch', 'slug' => 'school-lunch',
            'meta_title' => 'School SEO Title', 'meta_description' => 'School SEO description.',
        ]);
        $products = Product::factory()->count(13)->create();
        $category->products()->attach($products->pluck('id'));

        $this->get(route('categories.show', $category))->assertOk()
            ->assertSee('<title>School SEO Title</title>', false)
            ->assertSee('<meta name="description" content="School SEO description.">', false)
            ->assertSee('<link rel="canonical" href="'.route('categories.show', $category).'">', false)
            ->assertSee('aria-label="Breadcrumb"', false)->assertSee('BreadcrumbList')->assertSee('ItemList');
        $this->get(route('categories.show', [$category, 'page' => 2, 'utm_source' => 'test']))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('categories.show', $category).'?page=2">', false)
            ->assertDontSee('utm_source', false);
    }

    public function test_shop_pagination_has_a_self_referencing_canonical(): void
    {
        Product::factory()->count(13)->create();

        $this->get(route('products.index', ['page' => 2, 'utm_source' => 'test']))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('products.index').'?page=2">', false)
            ->assertDontSee('name="robots"', false);
    }

    public function test_sitemap_contains_only_public_indexable_catalogue_urls(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'visible']);
        $empty = ProductCategory::factory()->create(['slug' => 'empty']);
        $inactiveCategory = ProductCategory::factory()->create(['slug' => 'inactive', 'is_active' => false]);
        $product = Product::factory()->create(['slug' => 'visible-product']);
        $inactiveProduct = Product::factory()->create(['slug' => 'inactive-product', 'is_active' => false]);
        $category->products()->attach($product);
        $inactiveCategory->products()->attach($product);

        $response = $this->get(route('sitemap.xml'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'))->assertSee(route('products.index'))
            ->assertSee(route('categories.show', $category))->assertSee(route('products.show', $product->slug))
            ->assertDontSee(route('categories.show', $empty))->assertDontSee(route('categories.show', $inactiveCategory))
            ->assertDontSee(route('products.show', $inactiveProduct->slug))
            ->assertDontSee('/artwork/')->assertDontSee('/cart')->assertDontSee('/checkout')->assertDontSee('/orders/');
        $this->assertStringStartsWith('<?xml', trim($response->getContent()));
    }

    public function test_human_sitemap_groups_products_under_their_parent_collections(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'School & Lunch', 'slug' => 'school-lunch']);
        $product = Product::factory()->create(['name' => 'Small Plastic Lunchbox']);
        $category->products()->attach($product);

        $this->get(route('sitemap'))->assertOk()
            ->assertSee('Find your way')->assertSee('Shop')->assertSee('School &amp; Lunch', false)
            ->assertSee('Small Plastic Lunchbox')->assertSee('All personalised gifts')->assertSee('Customer information')
            ->assertSee('href="'.route('categories.show', $category).'"', false)
            ->assertSee('href="'.route('products.show', $product->slug).'"', false)
            ->assertSee('<link rel="canonical" href="'.route('sitemap').'">', false);
    }
}
