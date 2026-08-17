<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ReservedSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogueRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function tree(): array
    {
        $parent = ProductCategory::factory()->create(['name' => 'School & Everyday', 'slug' => 'school-everyday']);
        $child = ProductCategory::factory()->create([
            'name' => 'Personalised Water Bottles for Kids',
            'slug' => 'personalised-water-bottles-for-kids',
            'parent_id' => $parent->id,
        ]);

        return [$parent, $child];
    }

    public function test_top_level_and_nested_urls_resolve(): void
    {
        [$parent, $child] = $this->tree();

        $this->get('/school-everyday')->assertOk()->assertSee('School &amp; Everyday', false);
        $this->get('/school-everyday/personalised-water-bottles-for-kids')->assertOk()
            ->assertSee('Personalised Water Bottles for Kids');
    }

    public function test_a_subcategory_under_the_wrong_parent_is_not_found(): void
    {
        [$parent, $child] = $this->tree();
        ProductCategory::factory()->create(['name' => 'Pets & Family', 'slug' => 'pets-family']);

        $this->get('/pets-family/personalised-water-bottles-for-kids')->assertNotFound();
    }

    public function test_a_subcategory_is_not_reachable_at_the_top_level(): void
    {
        $this->tree();

        $this->get('/personalised-water-bottles-for-kids')->assertNotFound();
    }

    public function test_inactive_taxonomy_is_not_found(): void
    {
        [$parent, $child] = $this->tree();

        $child->update(['is_active' => false]);
        $this->get($child->url())->assertNotFound();

        $child->update(['is_active' => true]);
        $parent->update(['is_active' => false]);
        $this->get($parent->url())->assertNotFound();
        $this->get($child->url())->assertNotFound();
    }

    public function test_unknown_slugs_are_not_found(): void
    {
        $this->get('/not-a-category')->assertNotFound();
        $this->get('/not-a-category/nor-this')->assertNotFound();
    }

    /**
     * A category can never shadow a real application route: the route pattern
     * excludes reserved segments outright, so the router falls through.
     */
    public function test_fixed_application_routes_are_never_captured_by_the_catalogue(): void
    {
        $product = Product::factory()->create(['slug' => 'a-real-product']);

        $expectations = [
            '/products' => 'products.index',
            '/products/a-real-product' => 'products.show',
            '/account' => 'account.index',
            '/cart' => 'cart.index',
            '/checkout' => 'checkout.show',
            '/order-support' => 'order-support.create',
            '/login' => 'login',
            '/register' => 'register',
            '/sitemap' => 'sitemap',
            '/sitemap.xml' => 'sitemap.xml',
            '/faq' => 'information.faq',
            '/artwork/anything' => 'artwork.show',
        ];

        foreach ($expectations as $uri => $name) {
            $matched = Route::getRoutes()->match(\Illuminate\Http\Request::create($uri));
            $this->assertSame($name, $matched->getName(), "{$uri} should resolve to {$name}");
        }

        // Even with a category literally named "products", the fixed route wins.
        $this->get(route('products.show', 'a-real-product'))->assertOk();
    }

    public function test_admin_and_health_endpoints_are_not_captured(): void
    {
        $this->assertSame('filament.admin.auth.login', Route::getRoutes()->match(\Illuminate\Http\Request::create('/admin/login'))->getName());
        $this->get('/up')->assertOk();
    }

    public function test_every_root_level_route_segment_is_reserved(): void
    {
        $catalogueRoutes = ['catalogue.category', 'catalogue.subcategory'];

        $missing = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => in_array($route->getName(), $catalogueRoutes, true))
            ->map(fn ($route) => Str::before(ltrim($route->uri(), '/'), '/'))
            ->filter(fn ($segment) => $segment !== '' && ! Str::startsWith($segment, '{') && ! Str::contains($segment, '.'))
            ->unique()
            ->reject(fn ($segment) => ReservedSlugs::has($segment))
            ->values()->all();

        $this->assertSame([], $missing, 'Add these segments to ReservedSlugs::SLUGS: '.implode(', ', $missing));
    }

    public function test_every_public_directory_is_reserved(): void
    {
        $missing = collect(File::directories(public_path()))
            ->map(fn ($directory) => basename($directory))
            ->reject(fn ($directory) => ReservedSlugs::has($directory))
            ->values()->all();

        $this->assertSame([], $missing, 'Add these public directories to ReservedSlugs::SLUGS: '.implode(', ', $missing));
    }

    public function test_legacy_collection_urls_redirect_permanently(): void
    {
        $school = ProductCategory::factory()->create(['slug' => 'school-everyday']);
        $bottles = ProductCategory::factory()->create(['slug' => 'personalised-water-bottles-for-kids', 'parent_id' => $school->id]);
        $tins = ProductCategory::factory()->create(['slug' => 'personalised-pencil-tins', 'parent_id' => $school->id]);

        $this->get('/collections/school-lunch')->assertStatus(301)->assertRedirect($school->url());
        $this->get('/collections/kids-drinkware')->assertStatus(301)->assertRedirect($bottles->url());
        $this->get('/collections/school-accessories')->assertStatus(301)->assertRedirect($tins->url());
    }

    public function test_a_current_slug_still_redirects_from_the_legacy_prefix(): void
    {
        [$parent, $child] = $this->tree();

        $this->get('/collections/school-everyday')->assertStatus(301)->assertRedirect($parent->url());
        $this->get('/collections/personalised-water-bottles-for-kids')->assertStatus(301)->assertRedirect($child->url());
    }

    public function test_an_unknown_legacy_collection_is_not_found(): void
    {
        $this->get('/collections/never-existed')->assertNotFound();
    }

    public function test_no_collections_url_is_ever_canonical(): void
    {
        [$parent, $child] = $this->tree();
        $child->products()->attach(Product::factory()->create());

        foreach ([$parent->url(), $child->url(), route('products.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('canonical" href="'.url('/collections'), false);
        }
    }
}
