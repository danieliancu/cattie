<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_category_admin_remains_restricted_to_admins(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'school-everyday']);

        $this->get('/admin/product-categories')->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/product-categories')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/product-categories')->assertOk();
        $this->actingAs($this->admin())->get("/admin/product-categories/{$category->id}/edit")->assertOk();
    }

    public function test_admin_can_create_a_top_level_category(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateProductCategory::class)
            ->fillForm([
                'name' => 'School & Everyday',
                'slug' => 'school-everyday',
                'parent_id' => null,
                'short_description' => 'Make everyday things feel completely theirs.',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = ProductCategory::query()->where('slug', 'school-everyday')->firstOrFail();
        $this->assertNull($category->parent_id);
    }

    public function test_admin_can_create_a_child_subcategory(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);

        Livewire::actingAs($this->admin())
            ->test(CreateProductCategory::class)
            ->fillForm([
                'name' => 'Personalised Water Bottles for Kids',
                'slug' => 'personalised-water-bottles-for-kids',
                'parent_id' => $parent->id,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $child = ProductCategory::query()->where('slug', 'personalised-water-bottles-for-kids')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('/school-everyday/personalised-water-bottles-for-kids', parse_url($child->url(), PHP_URL_PATH));
    }

    public function test_the_parent_selector_offers_only_top_level_categories(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['name' => 'Water Bottles', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);

        $options = Livewire::actingAs($this->admin())
            ->test(CreateProductCategory::class)
            ->instance()
            ->form->getComponent('parent_id')
            ->getOptions();

        $this->assertArrayHasKey($parent->id, $options);
        // Offering the child would allow a third level.
        $this->assertArrayNotHasKey($child->id, $options);
    }

    public function test_the_parent_selector_excludes_the_record_being_edited(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);
        ProductCategory::factory()->create(['name' => 'Pets & Family', 'slug' => 'pets-family']);

        $options = Livewire::actingAs($this->admin())
            ->test(EditProductCategory::class, ['record' => $category->id])
            ->instance()
            ->form->getComponent('parent_id')
            ->getOptions();

        $this->assertArrayNotHasKey($category->id, $options);
    }

    public function test_a_reserved_top_level_slug_is_rejected_with_a_form_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateProductCategory::class)
            ->fillForm(['name' => 'Products', 'slug' => 'products', 'parent_id' => null, 'sort_order' => 0])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $this->assertSame(0, ProductCategory::query()->where('slug', 'products')->count());
    }

    public function test_a_category_with_children_cannot_be_turned_into_a_subcategory(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        ProductCategory::factory()->create(['slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $other = ProductCategory::factory()->create(['slug' => 'gifts-occasions']);

        Livewire::actingAs($this->admin())
            ->test(EditProductCategory::class, ['record' => $parent->id])
            ->fillForm(['parent_id' => $other->id])
            ->call('save');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_the_url_preview_reflects_the_hierarchy_and_uses_the_application_url(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);

        $component = Livewire::actingAs($this->admin())->test(CreateProductCategory::class);

        $component->fillForm(['slug' => 'school-everyday', 'parent_id' => null]);
        $this->assertSame(
            route('catalogue.category', ['categorySlug' => 'school-everyday']),
            $component->instance()->form->getComponent('url_preview')->getContent(),
        );

        $component->fillForm(['slug' => 'personalised-water-bottles-for-kids', 'parent_id' => $parent->id]);
        $this->assertSame(
            route('catalogue.subcategory', ['categorySlug' => 'school-everyday', 'subcategorySlug' => 'personalised-water-bottles-for-kids']),
            $component->instance()->form->getComponent('url_preview')->getContent(),
        );
    }

    public function test_the_category_table_shows_the_hierarchy(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday', 'sort_order' => 0]);
        $child = ProductCategory::factory()->create(['name' => 'Water Bottles', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);

        Livewire::actingAs($this->admin())
            ->test(ListProductCategories::class)
            ->assertCanSeeTableRecords([$parent, $child])
            ->assertTableColumnStateSet('type', 'Category', $parent)
            ->assertTableColumnStateSet('type', 'Subcategory', $child)
            ->assertTableColumnStateSet('path', '/school-everyday', $parent)
            ->assertTableColumnStateSet('path', '/school-everyday/water-bottles', $child);
    }

    public function test_the_product_category_selector_uses_parent_child_labels_and_leaf_records_only(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create(['name' => 'Personalised Water Bottles for Kids', 'slug' => 'water-bottles', 'parent_id' => $parent->id]);
        $standalone = ProductCategory::factory()->create(['name' => 'Standalone', 'slug' => 'standalone']);
        $product = Product::factory()->create();

        $options = Livewire::actingAs($this->admin())
            ->test(EditProduct::class, ['record' => $product->id])
            ->instance()
            ->form->getComponent('categories')
            ->getOptions();

        $this->assertSame('School & Everyday — Personalised Water Bottles for Kids', $options[$child->id]);
        // A category acting as a parent is not a valid product destination.
        $this->assertArrayNotHasKey($parent->id, $options);
        // A top-level category with no children is itself a leaf.
        $this->assertArrayHasKey($standalone->id, $options);
    }
}
