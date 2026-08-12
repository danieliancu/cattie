<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\SyncProductMarketingAssets;
use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncProductMarketingAssetsTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->sourceDirectory = storage_path('framework/testing/marketing-assets-'.uniqid());
        File::ensureDirectoryExists($this->sourceDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceDirectory);

        parent::tearDown();
    }

    public function test_it_discovers_supported_images_assigns_roles_and_is_idempotent(): void
    {
        [$product, $white] = $this->productWithVariant('White');
        $folder = $this->sourceDirectory.'/WHITE';
        File::ensureDirectoryExists($folder);
        foreach (['gift-hero.webp', 'gift-school.JPG', 'gift-home.jpeg', 'gift-outdoor.png'] as $filename) {
            File::put($folder.'/'.$filename, 'image-'.$filename);
        }
        foreach (['README.md', 'design.psd', '.hidden.png', '~temporary.png'] as $filename) {
            File::put($folder.'/'.$filename, 'ignored');
        }

        $sync = app(SyncProductMarketingAssets::class);
        $report = $sync->handle($product, $this->sourceDirectory);
        $sync->handle($product, $this->sourceDirectory);

        $this->assertSame(['primary', 'school', 'home', 'street'], collect($report)->pluck('role')->all());
        $this->assertSame(4, $product->images()->count());
        $this->assertSame([$white->id], $product->images()->pluck('product_variant_id')->unique()->all());
        $this->assertSame('products/'.$product->slug.'/catalogue/white/gift-hero.webp', $product->primaryImage()->storage_key);
        Storage::disk('public')->assertMissing('products/'.$product->slug.'/catalogue/white/.hidden.png');
    }

    public function test_it_uses_a_deterministic_fallback_and_removes_only_stale_managed_assets(): void
    {
        Log::spy();
        [$product] = $this->productWithVariant('White');
        $folder = $this->sourceDirectory.'/white';
        File::ensureDirectoryExists($folder);
        File::put($folder.'/alpha.png', 'alpha');
        File::put($folder.'/zulu.png', 'zulu');
        $unmanaged = $product->images()->create([
            'disk' => 'public',
            'storage_key' => 'products/'.$product->slug.'/supplier/original.jpg',
            'alt_text' => 'Supplier image',
            'sort_order' => 99,
        ]);
        Storage::disk('public')->put($unmanaged->storage_key, 'supplier');

        $sync = app(SyncProductMarketingAssets::class);
        $sync->handle($product, $this->sourceDirectory);
        File::delete($folder.'/zulu.png');
        $sync->handle($product, $this->sourceDirectory);

        $this->assertDatabaseHas('product_images', ['storage_key' => 'products/'.$product->slug.'/catalogue/white/alpha.png', 'sort_order' => 0]);
        $this->assertDatabaseMissing('product_images', ['storage_key' => 'products/'.$product->slug.'/catalogue/white/zulu.png']);
        $this->assertDatabaseHas('product_images', ['id' => $unmanaged->id]);
        Storage::disk('public')->assertExists($unmanaged->storage_key);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'no explicit primary'))->atLeast()->once();
    }

    public function test_it_rejects_unknown_and_ambiguous_variant_folders(): void
    {
        [$product] = $this->productWithVariant('White');
        File::ensureDirectoryExists($this->sourceDirectory.'/green');
        File::put($this->sourceDirectory.'/green/hero.png', 'image');

        try {
            app(SyncProductMarketingAssets::class)->handle($product, $this->sourceDirectory);
            $this->fail('An unknown colour folder should fail.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        File::deleteDirectory($this->sourceDirectory.'/green');
        ProductVariant::factory()->for($product)->create(['name' => 'Another white', 'options' => ['colour' => 'white']]);
        File::ensureDirectoryExists($this->sourceDirectory.'/white');
        File::put($this->sourceDirectory.'/white/hero.png', 'image');

        $this->expectException(DomainException::class);
        app(SyncProductMarketingAssets::class)->handle($product, $this->sourceDirectory);
    }

    /** @return array{Product, ProductVariant} */
    private function productWithVariant(string $colour): array
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'name' => $colour,
            'options' => ['colour' => mb_strtolower($colour)],
        ]);

        return [$product, $variant];
    }
}
