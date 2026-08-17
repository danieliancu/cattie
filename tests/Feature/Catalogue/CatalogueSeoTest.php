<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSeoTest extends TestCase
{
    use RefreshDatabase;

    private function tree(array $parentAttributes = [], array $childAttributes = []): array
    {
        $parent = ProductCategory::factory()->create($parentAttributes + [
            'name' => 'School & Everyday', 'slug' => 'school-everyday', 'parent_id' => null,
        ]);
        $child = ProductCategory::factory()->create($childAttributes + [
            'name' => 'Personalised Water Bottles for Kids',
            'slug' => 'personalised-water-bottles-for-kids',
            'parent_id' => $parent->id,
        ]);

        return [$parent, $child];
    }

    public function test_subcategory_metadata_canonical_breadcrumbs_and_pagination_are_correct(): void
    {
        [$parent, $child] = $this->tree([], [
            'meta_title' => 'Water Bottles SEO Title', 'meta_description' => 'Water bottles SEO description.',
        ]);
        $child->products()->attach(Product::factory()->count(13)->create()->pluck('id'));

        $this->get($child->url())->assertOk()
            ->assertSee('<title>Water Bottles SEO Title</title>', false)
            ->assertSee('<meta name="description" content="Water bottles SEO description.">', false)
            ->assertSee('<link rel="canonical" href="'.$child->url().'">', false)
            ->assertSee('aria-label="Breadcrumb"', false)->assertSee('BreadcrumbList')->assertSee('ItemList');

        // Tracking parameters are carried by the pagination links but must never
        // leak into the canonical.
        $this->get($child->url().'?page=2&utm_source=test')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$child->url().'?page=2">', false)
            ->assertDontSee('canonical" href="'.$child->url().'?page=2&amp;utm_source', false);
    }

    public function test_category_and_subcategory_meta_fall_back_to_name_and_short_description(): void
    {
        [$parent, $child] = $this->tree(
            ['meta_title' => null, 'meta_description' => null, 'short_description' => 'Everyday things, made theirs.'],
            ['meta_title' => null, 'meta_description' => null, 'short_description' => 'Bottles made for them.'],
        );

        $this->get($parent->url())->assertOk()
            ->assertSee('<title>School &amp; Everyday | Personalised Gifts | Kattie.uk</title>', false)
            ->assertSee('<meta name="description" content="Everyday things, made theirs.">', false);

        $this->get($child->url())->assertOk()
            ->assertSee('<title>Personalised Water Bottles for Kids | Kattie.uk</title>', false)
            ->assertSee('<meta name="description" content="Bottles made for them.">', false);
    }

    public function test_sitemap_contains_only_public_indexable_catalogue_urls(): void
    {
        [$parent, $populated] = $this->tree();
        $empty = ProductCategory::factory()->create(['slug' => 'personalised-lunch-bags-for-kids', 'parent_id' => $parent->id]);
        $inactiveChild = ProductCategory::factory()->create(['slug' => 'inactive-child', 'parent_id' => $parent->id, 'is_active' => false]);
        $inactiveParent = ProductCategory::factory()->create(['slug' => 'inactive-parent', 'parent_id' => null, 'is_active' => false]);

        $product = Product::factory()->create(['slug' => 'visible-product']);
        $inactiveProduct = Product::factory()->create(['slug' => 'inactive-product', 'is_active' => false]);
        $populated->products()->attach($product);
        $inactiveChild->products()->attach($product);

        $response = $this->get(route('sitemap.xml'))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'))->assertSee(route('products.index'))
            // Top-level categories are indexable on their own copy and hierarchy.
            ->assertSee($parent->url())
            // A subcategory enters the sitemap only once it has an active product.
            ->assertSee($populated->url())
            ->assertSee(route('products.show', $product->slug))
            ->assertDontSee($empty->url())
            ->assertDontSee($inactiveChild->url())
            ->assertDontSee($inactiveParent->url())
            ->assertDontSee(route('products.show', $inactiveProduct->slug))
            ->assertDontSee('/collections/')
            ->assertDontSee('/artwork/')->assertDontSee('/cart')->assertDontSee('/checkout')->assertDontSee('/orders/');

        $this->assertStringStartsWith('<?xml', trim($response->getContent()));
    }

    public function test_empty_subcategory_is_noindex_until_it_has_a_product(): void
    {
        [, $child] = $this->tree();

        $this->get($child->url())->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.$child->url().'">', false);
        $this->get(route('sitemap.xml'))->assertOk()->assertDontSee($child->url());

        $child->products()->attach(Product::factory()->create());

        // No manual SEO toggle: assigning a product flips both behaviours.
        $this->get($child->url())->assertOk()->assertDontSee('name="robots"', false);
        $this->get(route('sitemap.xml'))->assertOk()->assertSee($child->url());
    }

    public function test_human_sitemap_shows_the_category_hierarchy(): void
    {
        [$parent, $child] = $this->tree();

        $this->get(route('sitemap'))->assertOk()
            ->assertSee('Find your way')->assertSee('Shop')
            ->assertSee('School &amp; Everyday', false)
            ->assertSee('Personalised Water Bottles for Kids')
            ->assertSee('All personalised gifts')->assertSee('Customer information')
            ->assertSee('href="'.$parent->url().'"', false)
            ->assertSee('href="'.$child->url().'"', false)
            ->assertSee('<link rel="canonical" href="'.route('sitemap').'">', false);
    }

    public function test_shop_pagination_has_a_self_referencing_canonical(): void
    {
        Product::factory()->count(13)->create();

        $this->get(route('products.index', ['page' => 2, 'utm_source' => 'test']))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('products.index').'?page=2">', false)
            ->assertDontSee('name="robots"', false);
    }
}
