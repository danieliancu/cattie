<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Jobs\RenderComposedDesignPrint;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenderComposedDesignPrintJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '256M');
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CatalogueSeeder::class);
    }

    public function test_job_renders_full_print_file_for_an_approved_preview_design(): void
    {
        [$session, $asset] = $this->inputs();

        // A preview design is persisted without the full-resolution print file.
        $design = app(RenderComposedDesign::class)->handlePreview($session, $asset);
        $this->assertNull($design->storage_key);
        $this->assertLessThanOrEqual(1200, max($design->width, $design->height));

        (new RenderComposedDesignPrint($design))->handle(app(RenderComposedDesign::class));

        $design->refresh();
        $this->assertNotNull($design->storage_key, 'The job must attach the full print file.');
        $this->assertStringEndsWith('.png', $design->storage_key);
        Storage::disk('local')->assertExists($design->storage_key);

        // The stored PNG is at the full print resolution (2717x2008 for the water bottle).
        $this->assertSame([2717, 2008], [$design->width, $design->height]);
        $pngBytes = Storage::disk('local')->get($design->storage_key);
        $this->assertSame([2717, 2008], array_slice(getimagesizefromstring($pngBytes), 0, 2));
        $print = imagecreatefromstring($pngBytes);
        $this->assertSame([300, 300], imageresolution($print));
        imagedestroy($print);
    }

    public function test_job_is_idempotent_once_the_print_file_exists(): void
    {
        [$session, $asset] = $this->inputs();
        $design = app(RenderComposedDesign::class)->handlePreview($session, $asset);

        (new RenderComposedDesignPrint($design))->handle(app(RenderComposedDesign::class));
        $design->refresh();
        $firstKey = $design->storage_key;
        $this->assertNotNull($firstKey);

        // A second run must not re-render or repoint the stored file.
        (new RenderComposedDesignPrint($design))->handle(app(RenderComposedDesign::class));
        $this->assertSame($firstKey, $design->fresh()->storage_key);
    }

    private function inputs(string $colour = 'white', string $token = 'print-owner'): array
    {
        $product = Product::query()->where('slug', 'water-bottle-with-red-flip-lid')->with(['variants', 'artworkStyles'])->firstOrFail();
        $variant = $product->variants->first(fn ($variant) => $variant->options['colour'] === $colour);
        $style = $product->artworkStyles->first();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Maria']], $token);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'source-'.uniqid().'.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => hash('sha256', uniqid())]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        $key = 'generated/'.uniqid().'.png';
        Storage::disk('local')->put($key, file_get_contents(database_path('seeders/assets/fake-artwork/fake-artwork-a.png')));
        $asset = $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/png']);
        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        return [$session->fresh(['product.variants', 'variant']), $asset];
    }
}
