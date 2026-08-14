<?php

namespace Tests\Feature\Admin;

use App\Domain\Artwork\Actions\PublishDesignTemplateVersion;
use App\Domain\Artwork\Actions\ResolveDesignTemplateVersion;
use App\Domain\Artwork\Actions\TestRenderDesignTemplateVersion;
use App\Domain\Catalogue\Actions\BootstrapAdminCatalogue;
use App\Domain\Catalogue\Actions\DuplicateProduct;
use App\Domain\Catalogue\Actions\ProductPublishReadiness;
use App\Domain\Fulfilment\Providers\ManualTreatPodCatalogueProvider;
use App\Enums\DesignTemplateVersionStatus;
use App\Enums\ProductStatus;
use App\Jobs\SyncProviderVariant;
use App\Models\DesignTemplateVersion;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use LogicException;
use Tests\TestCase;

class CattieAdminV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        app(BootstrapAdminCatalogue::class)->handle();
    }

    public function test_admin_panel_is_private(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/product-categories')->assertOk();
        $this->get('/admin/product-design-templates')->assertOk();
        $this->get('/admin/fulfilment-product-mappings')->assertOk();
        $this->get('/admin/shipping-methods')->assertOk();
        $this->get('/admin/shipping-methods/create')->assertOk();
        $this->get('/admin/admin-audit-logs')->assertOk();
        $this->get('/admin/products/'.$this->pencil()->id.'/edit')->assertOk();
        $this->get('/admin/product-design-templates/'.$this->pencil()->product_design_template_id.'/edit')->assertOk();
    }

    public function test_existing_catalogue_and_templates_are_imported_idempotently(): void
    {
        $product = $this->pencil();
        $this->assertSame(ProductStatus::Published, $product->status);
        $this->assertTrue($product->is_active);
        $this->assertNotNull($product->default_variant_id);
        $this->assertSame(1, $product->designTemplateAssignments()->count());
        $count = DesignTemplateVersion::count();
        app(BootstrapAdminCatalogue::class)->handle();
        $this->assertSame($count, DesignTemplateVersion::count());
        $version = app(ResolveDesignTemplateVersion::class)->handle($product, $product->defaultVariant);
        $this->assertSame(DesignTemplateVersionStatus::Published, $version->status);
        $this->assertSame('succeeded', $version->last_test_render_status);
    }

    public function test_readiness_duplicate_slug_redirect_and_preview_security(): void
    {
        $product = $this->pencil();
        $this->assertTrue(app(ProductPublishReadiness::class)->ready($product));
        $copy = app(DuplicateProduct::class)->handle($product);
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertFalse($copy->is_active);
        $this->assertNotSame($product->slug, $copy->slug);
        $this->assertSame(3, $copy->variants()->count());
        $this->assertTrue($copy->variants()->with('fulfilmentMappings')->get()->flatMap->fulfilmentMappings->every(fn ($m) => ! $m->is_active && $m->provider_sku === ''));
        $old = $product->slug;
        $product->update(['slug' => 'renamed-pencil-tin']);
        $this->assertDatabaseHas('product_slug_redirects', ['old_slug' => $old, 'product_id' => $product->id]);
        $this->get('/products/'.$old)->assertRedirect('/products/renamed-pencil-tin')->assertStatus(301);
        $url = URL::temporarySignedRoute('admin.products.preview', now()->addMinutes(5), ['product' => $copy]);
        $this->get($url)->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('noindex,nofollow')->assertDontSee('rel="canonical"', false);
    }

    public function test_published_template_is_immutable_and_provider_failure_is_stored(): void
    {
        $version = DesignTemplateVersion::where('status', 'published')->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['configuration' => ['changed' => true]]);
    }

    public function test_manual_provider_sync_failure_does_not_change_retail_price(): void
    {
        $product = $this->pencil();
        $mapping = $product->defaultVariant->fulfilmentMappings()->firstOrFail();
        $price = $product->default_price_minor;
        (new SyncProviderVariant($mapping->id))->handle(app(ManualTreatPodCatalogueProvider::class));
        $this->assertSame('failed', $mapping->fresh()->last_sync_status);
        $this->assertSame($price, $product->fresh()->default_price_minor);
    }

    public function test_draft_template_uses_private_real_test_render_before_publication(): void
    {
        $published = DesignTemplateVersion::whereHas('template', fn ($q) => $q->where('key', 'stationery-pencil-tin-v1'))->where('status', 'published')->firstOrFail();
        $draft = $published->replicate(['status', 'published_at', 'last_test_render_at', 'last_test_render_status', 'last_test_render_error', 'test_render_disk', 'test_render_storage_key']);
        $draft->version = $published->version + 1;
        $draft->status = DesignTemplateVersionStatus::Draft;
        $draft->save();
        $source = 'admin-tests/character.png';
        Storage::disk('local')->put($source, file_get_contents(database_path('seeders/assets/fake-artwork/fake-artwork-a.png')));
        app(TestRenderDesignTemplateVersion::class)->handle($draft, $this->pencil()->defaultVariant, 'local', $source, ['name' => 'Sophie']);
        $this->assertSame('succeeded', $draft->fresh()->last_test_render_status);
        Storage::disk('local')->assertExists($draft->fresh()->test_render_storage_key);
        app(PublishDesignTemplateVersion::class)->handle($draft->fresh());
        $this->assertSame(DesignTemplateVersionStatus::Published, $draft->fresh()->status);
        $this->assertSame(DesignTemplateVersionStatus::Retired, $published->fresh()->status);
    }

    private function pencil(): Product
    {
        return Product::where('slug', 'personalised-stationery-pencil-tin')->with(['variants.fulfilmentMappings', 'defaultVariant.fulfilmentMappings', 'images', 'categories', 'personalisationFields', 'artworkStyles', 'designTemplateAssignments.version'])->firstOrFail();
    }
}
