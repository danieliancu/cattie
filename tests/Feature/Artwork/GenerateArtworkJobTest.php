<?php

namespace Tests\Feature\Artwork;

use App\Contracts\BackgroundRemovalRunner;
use App\Contracts\ImageGenerationProvider;
use App\Data\ImageGenerationRequest;
use App\Data\ImageGenerationResult;
use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\ArtworkProcessingStage;
use App\Enums\GenerationStatus;
use App\Exceptions\ImageGenerationException;
use App\Jobs\GenerateArtwork;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Providers\ImageGeneration\FakeImageGenerationProvider;
use App\Services\AiGenerationCostCalculator;
use App\Services\BackgroundRemovalProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateArtworkJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_provider_success_persists_original_preview_usage_and_cost(): void
    {
        Storage::fake('local');
        $generation = $this->generation();

        (new GenerateArtwork($generation))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('unavailable', $generation->cost_basis);
        $this->assertNull($generation->cost_micros);
        $this->assertSame('fake-'.$generation->id, $generation->provider_request_id);
        $this->assertEqualsCanonicalizing(['provider_original', 'composition_source', 'web_preview'], $generation->assets->pluck('kind')->all());
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $generation->artworkSession->status);
        $this->assertSame(ArtworkProcessingStage::Ready, $generation->artworkSession->processing_stage);
    }

    public function test_permanent_provider_failure_is_recorded_without_exposing_exception(): void
    {
        Storage::fake('local');
        config(['artwork.fake_failure' => true]);
        $generation = $this->generation();

        (new GenerateArtwork($generation))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame('simulated', $generation->fresh()->failure_category);
        $this->assertSame(ArtworkSessionStatus::Failed, $generation->artworkSession->fresh()->status);
    }

    public function test_missing_frozen_style_reference_sets_consistent_terminal_states(): void
    {
        Storage::fake('local');
        $generation = $this->generation();
        config(['artwork.style_references.storybook-cartoon-v4.path' => base_path('missing-style-reference.png')]);

        (new GenerateArtwork($generation))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame('configuration', $generation->fresh()->failure_category);
        $this->assertSame('invalid_style_reference', $generation->fresh()->provider_error_code);
        $this->assertFalse($generation->fresh()->is_retryable);
        $this->assertSame(ArtworkSessionStatus::Failed, $generation->artworkSession->fresh()->status);
        $this->assertDatabaseCount('generation_assets', 0);
    }

    public function test_fake_provider_uses_three_deterministic_visible_variants(): void
    {
        Storage::fake('local');
        $variants = [];
        for ($sequence = 1; $sequence <= 3; $sequence++) {
            $generation = $this->generation();
            $generation->update(['parameters' => ['generation_sequence' => $sequence]]);
            (new GenerateArtwork($generation))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));
            $variants[] = $generation->fresh()->parameters['provider']['variant'];
        }

        $this->assertSame(['a', 'b', 'c'], $variants);
        $this->assertCount(3, array_unique($variants));
    }

    public function test_fake_failure_can_recover_through_immutable_regeneration(): void
    {
        Storage::fake('local');
        config(['artwork.fake_failure' => true]);
        $first = $this->generation();
        (new GenerateArtwork($first))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));
        $this->assertSame(GenerationStatus::Failed, $first->fresh()->status);

        config(['artwork.fake_failure' => false]);
        $second = app(RequestArtworkGeneration::class)->handle($first->artworkSession->fresh(), true);
        (new GenerateArtwork($second))->handle(new FakeImageGenerationProvider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class));

        $this->assertSame($first->id, $second->parent_generation_id);
        $this->assertSame(GenerationStatus::Succeeded, $second->fresh()->status);
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $second->artworkSession->fresh()->status);
        $this->assertDatabaseCount('generations', 2);
    }

    public function test_background_removal_failure_creates_no_partial_assets_and_sets_terminal_states(): void
    {
        Storage::fake('local');
        $generation = $this->generation();
        $generation->update(['parameters' => array_merge($generation->parameters, ['output_requirements' => ['transparent_background' => true]])]);
        $provider = new class implements ImageGenerationProvider
        {
            public function generate(ImageGenerationRequest $request): ImageGenerationResult
            {
                $image = imagecreatetruecolor(30, 30);
                imagefill($image, 0, 0, imagecolorallocate($image, 200, 150, 100));
                ob_start();
                imagepng($image);
                $bytes = ob_get_clean();
                imagedestroy($image);

                return new ImageGenerationResult($bytes);
            }
        };
        $runner = \Mockery::mock(BackgroundRemovalRunner::class);
        $runner->shouldReceive('run')->once()->andThrow(new ImageGenerationException('failed', 'post_processing', 'background_removal_failed', false));

        (new GenerateArtwork($generation))->handle($provider, new AiGenerationCostCalculator, app(RecordAnalyticsEvent::class), null, new BackgroundRemovalProcessor($runner));

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame(ArtworkSessionStatus::Failed, $generation->artworkSession->fresh()->status);
        $this->assertSame('background_removal_failed', $generation->fresh()->provider_error_code);
        $this->assertDatabaseCount('generation_assets', 0);
        Storage::disk('local')->assertMissing('artwork/generated');
    }

    private function generation()
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->firstOrCreate(['slug' => 'storybook-cartoon'], ['name' => 'Storybook', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id]);
        $suffix = bin2hex(random_bytes(6));
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => "original-{$suffix}.jpg", 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => hash('sha256', $suffix)]);
        $upload->assets()->create(['kind' => 'ai_input', 'disk' => 'local', 'storage_key' => "input-{$suffix}.webp", 'mime_type' => 'image/webp', 'size_bytes' => 1, 'width' => 800, 'height' => 1000]);
        $session->update(['current_upload_id' => $upload->id]);
        Queue::fake();

        return app(RequestArtworkGeneration::class)->handle($session);
    }
}
