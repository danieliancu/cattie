<?php

namespace Tests\Feature\Catalogue;

use App\Exceptions\InvalidCategoryHierarchyException;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_level_category_has_no_parent_and_owns_its_children(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $first = ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $second = ProductCategory::factory()->create(['slug' => 'lunch-boxes', 'parent_id' => $parent->id]);

        $this->assertNull($parent->parent_id);
        $this->assertTrue($parent->isTopLevel());
        $this->assertFalse($first->isTopLevel());
        $this->assertSame($parent->id, $first->parent->id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $parent->children->modelKeys());
    }

    public function test_a_third_level_is_rejected(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);

        $this->expectException(InvalidCategoryHierarchyException::class);
        $this->expectExceptionMessage('two levels deep');
        ProductCategory::factory()->create(['slug' => 'metal-bottles', 'parent_id' => $child->id]);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'school-everyday']);

        $this->expectException(InvalidCategoryHierarchyException::class);
        $this->expectExceptionMessage('its own parent');
        $category->update(['parent_id' => $category->id]);
    }

    public function test_a_category_with_children_cannot_be_given_a_parent(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $other = ProductCategory::factory()->create(['slug' => 'gifts-occasions']);

        $this->expectException(InvalidCategoryHierarchyException::class);
        $this->expectExceptionMessage('has subcategories');
        $parent->update(['parent_id' => $other->id]);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);

        try {
            $parent->delete();
            $this->fail('Expected the delete to be refused.');
        } catch (InvalidCategoryHierarchyException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertDatabaseHas('product_categories', ['id' => $parent->id]);
    }

    public function test_reserved_top_level_slugs_are_rejected(): void
    {
        $this->expectException(InvalidCategoryHierarchyException::class);
        $this->expectExceptionMessage('reserved');
        ProductCategory::factory()->create(['slug' => 'products']);
    }

    public function test_a_reserved_slug_is_allowed_for_a_subcategory(): void
    {
        // Subcategories never occupy a first path segment, so they cannot collide.
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['slug' => 'products', 'parent_id' => $parent->id]);

        $this->assertSame('/school-everyday/products', parse_url($child->url(), PHP_URL_PATH));
    }

    public function test_malformed_slugs_are_rejected(): void
    {
        $this->expectException(InvalidCategoryHierarchyException::class);
        ProductCategory::factory()->create(['slug' => 'Not A Slug']);
    }

    public function test_ordering_applies_to_parents_and_children(): void
    {
        $second = ProductCategory::factory()->create(['slug' => 'pets-family', 'sort_order' => 1]);
        $first = ProductCategory::factory()->create(['slug' => 'school-everyday', 'sort_order' => 0]);
        ProductCategory::factory()->create(['slug' => 'lunch-boxes', 'parent_id' => $first->id, 'sort_order' => 1]);
        ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $first->id, 'sort_order' => 0]);

        $tree = \App\Support\CatalogueNavigation::topLevelWithChildren();

        $this->assertSame(['school-everyday', 'pets-family'], $tree->pluck('slug')->all());
        $this->assertSame(['water-bottles', 'lunch-boxes'], $tree->first()->children->pluck('slug')->all());
    }

    public function test_one_product_can_belong_to_several_leaf_subcategories(): void
    {
        $school = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $gifts = ProductCategory::factory()->create(['slug' => 'gifts-occasions']);
        $bottles = ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $school->id]);
        $birthdays = ProductCategory::factory()->create(['slug' => 'birthday-gifts', 'parent_id' => $gifts->id]);
        $christmas = ProductCategory::factory()->create(['slug' => 'christmas-gifts', 'parent_id' => $gifts->id]);

        $product = Product::factory()->create(['name' => 'Personalised Kids Water Bottle', 'slug' => 'kids-bottle']);
        $product->categories()->attach([$bottles->id, $birthdays->id, $christmas->id]);

        $this->assertCount(3, $product->fresh()->categories);

        // One product, one record, one canonical URL — reached from every collection.
        $this->assertSame(1, Product::query()->where('slug', 'kids-bottle')->count());
        foreach ([$bottles, $birthdays, $christmas] as $collection) {
            $this->get($collection->url())->assertOk()
                ->assertSee('href="'.route('products.show', 'kids-bottle').'"', false);
        }
        $this->get(route('products.show', 'kids-bottle'))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('products.show', 'kids-bottle').'">', false);
    }

    public function test_category_urls_reflect_their_level(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);

        $this->assertSame('/school-everyday', parse_url($parent->url(), PHP_URL_PATH));
        $this->assertSame('/school-everyday/water-bottles', parse_url($child->url(), PHP_URL_PATH));
        $this->assertSame($child->url(), ProductCategory::urlFor('school-everyday', 'water-bottles'));
        $this->assertSame($parent->url(), ProductCategory::urlFor(null, 'school-everyday'));
    }
}
