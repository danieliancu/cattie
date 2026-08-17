<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_use_ulids_and_products_have_many_to_many_membership(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $first = ProductCategory::factory()->create(['slug' => 'first', 'sort_order' => 1]);
        $second = ProductCategory::factory()->create(['slug' => 'second', 'sort_order' => 0]);
        $product->categories()->attach([$first->id => ['sort_order' => 1], $second->id => ['sort_order' => 0]]);
        $first->products()->attach($other, ['sort_order' => 1]);

        $this->assertSame(26, strlen($first->id));
        $this->assertCount(2, $product->categories);
        $this->assertCount(2, $first->products);
        $this->assertSame('second', $product->categories->first()->slug);

        $this->expectException(QueryException::class);
        $product->categories()->attach($first);
    }

    public function test_category_slug_is_unique(): void
    {
        ProductCategory::factory()->create(['slug' => 'school']);

        $this->expectException(QueryException::class);
        ProductCategory::factory()->create(['slug' => 'school']);
    }

    public function test_subcategory_pages_are_active_only_and_hide_inactive_products(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['name' => 'Water Bottles', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $active = Product::factory()->create(['name' => 'Active Bottle']);
        $inactive = Product::factory()->create(['name' => 'Hidden Bottle', 'is_active' => false]);
        $child->products()->attach([$active->id, $inactive->id]);

        $this->get($child->url())->assertOk()
            ->assertSee('Water Bottles')->assertSee('Active Bottle')->assertDontSee('Hidden Bottle')
            ->assertSee('Create yours')->assertSee('application/ld+json', false);

        $child->update(['is_active' => false]);
        $this->get($child->url())->assertNotFound();

        // Deactivating the parent must take its children out of circulation too.
        $child->update(['is_active' => true]);
        $parent->update(['is_active' => false]);
        $this->get($child->url())->assertNotFound();
        $this->get($parent->url())->assertNotFound();
    }

    public function test_seeded_products_move_into_leaf_subcategories_without_changing_variants(): void
    {
        Storage::fake('public');
        $this->seed(CatalogueSeeder::class);
        $product = Product::query()->where('slug', 'water-bottle-with-red-flip-lid')->firstOrFail();
        $variantIds = $product->variants()->active()->pluck('id')->all();

        $this->assertSame(['personalised-water-bottles-for-kids'], $product->categories->pluck('slug')->all());
        $this->assertSame(['white', 'silver'], $product->variants()->active()->ordered()->get()->pluck('options')->pluck('colour')->all());
        $this->assertGreaterThanOrEqual(8, $product->images()->count());

        $this->get('/school-everyday')->assertOk()->assertSee('Personalised Water Bottles for Kids');
        $this->get('/school-everyday/personalised-water-bottles-for-kids')->assertOk()->assertSee('Water Bottle with Red Flip Lid');
        $this->get(route('sitemap.xml'))->assertOk()
            ->assertSee('/school-everyday')
            ->assertSee('/school-everyday/personalised-water-bottles-for-kids')
            ->assertDontSee('/products/cattie-water-bottle');

        // The retired flat taxonomy must not survive alongside the new structure.
        $this->assertSame(0, ProductCategory::query()->whereIn('slug', ['school-lunch', 'kids-drinkware', 'school-accessories'])->count());

        $this->seed(CatalogueSeeder::class);
        $this->assertSame(4, ProductCategory::query()->whereNull('parent_id')->count());
        $this->assertSame(20, ProductCategory::query()->whereNotNull('parent_id')->count());
        $this->assertSame(1, $product->fresh()->categories()->count());
        $this->assertSame($variantIds, $product->fresh()->variants()->active()->pluck('id')->all());
        $this->assertGreaterThanOrEqual(8, $product->fresh()->images()->count());
    }

    public function test_navigation_shows_the_hierarchy_including_categories_without_products(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday', 'sort_order' => 0]);
        $withProducts = ProductCategory::factory()->create(['name' => 'Water Bottles', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $empty = ProductCategory::factory()->create(['name' => 'School Backpacks', 'slug' => 'school-backpacks', 'parent_id' => $parent->id]);
        $hidden = ProductCategory::factory()->create(['name' => 'Hidden Category', 'slug' => 'hidden', 'is_active' => false]);
        $product = Product::factory()->create(['name' => 'Special Bottle']);
        ProductVariant::factory()->for($product)->create();
        $product->categories()->attach([$withProducts->id, $hidden->id]);

        // Empty categories must stay visible: the structure ships before the products do.
        $this->get(route('products.index'))->assertOk()->assertSee('Shop by category')
            ->assertSee('School &amp; Everyday', false)
            ->assertSee('School Backpacks')
            ->assertDontSee('Hidden Category')
            ->assertSee('href="'.$empty->url().'"', false)
            ->assertDontSee('name="robots"', false);

        $this->get(route('products.index', ['q' => 'School']))->assertOk()->assertSee('Special Bottle')
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('products.index').'">', false);

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('Categories:')->assertSee('Water Bottles')->assertDontSee('Hidden Category')
            ->assertSee('href="'.$withProducts->url().'"', false)
            ->assertSee('<link rel="canonical" href="'.route('products.show', $product->slug).'">', false);
    }

    public function test_homepage_shows_exactly_the_top_level_categories(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday', 'sort_order' => 0]);
        ProductCategory::factory()->create(['name' => 'Water Bottles', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);
        ProductCategory::factory()->create(['name' => 'Pets & Family', 'slug' => 'pets-family', 'sort_order' => 1]);

        $content = $this->get(route('home'))->assertOk()
            ->assertSee('Where would you like to start?')
            ->assertSee('href="'.$parent->url().'"', false)
            ->getContent();

        // The homepage grid lists the top-level categories only. Subcategories
        // stay in the header menu and on the category pages themselves.
        $section = Str::between($content, 'Where would you like to start?', '</section>');
        $this->assertStringNotContainsString(route('catalogue.subcategory', ['school-everyday', 'water-bottles']), $section);
        $this->assertSame(2, substr_count($section, 'rounded-full'));
    }

    public function test_related_products_prefer_shared_categories_then_fill_catalogue_order(): void
    {
        $category = ProductCategory::factory()->create();
        $current = Product::factory()->create(['name' => 'Current', 'sort_order' => 1]);
        $fallback = Product::factory()->create(['name' => 'Fallback', 'sort_order' => 2]);
        $shared = Product::factory()->create(['name' => 'Shared', 'sort_order' => 99]);
        $category->products()->attach([$current->id, $shared->id]);

        $content = $this->get(route('products.show', $current->slug))->assertOk()->getContent();
        $this->assertLessThan(strrpos($content, 'Fallback'), strrpos($content, 'Shared'));
    }
}
