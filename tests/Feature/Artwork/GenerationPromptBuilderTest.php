<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\GenerationPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerationPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_storybook_v5_separates_customer_content_and_conditionally_preserves_pose(): void
    {
        [$session] = $this->createArtworkSession('storybook-cartoon', [
            'orientation' => 'portrait',
            'framing' => 'full_body',
            'isolated_subject' => true,
            'transparent_background' => true,
        ]);

        $resolved = app(GenerationPromptBuilder::class)->build($session);

        $this->assertSame('storybook-v5', $resolved['key']);
        $this->assertSame(5, $resolved['version']);
        $this->assertTrue($resolved['output_requirements']['transparent_background']);
        $this->assertSame('storybook-cartoon-v4', $resolved['input_references'][0]['key']);
        $this->assertSame('style_only', $resolved['input_references'][0]['role']);
        $this->assertStringContainsString('IMAGE 1 is the customer photo and is the only CONTENT SOURCE', $resolved['prompt']);
        $this->assertStringContainsString('IMAGE 2 is a STYLE REFERENCE ONLY', $resolved['prompt']);
        $this->assertStringContainsString('if IMAGE 1 clearly shows a complete, natural, usable body pose, preserve that pose and body position', $resolved['prompt']);
        $this->assertStringContainsString('body is only partially visible, hidden, cropped, ambiguous', $resolved['prompt']);
        $this->assertStringContainsString('front-facing neutral standing pose with arms relaxed at the sides', $resolved['prompt']);
        $this->assertStringContainsString('Scale a wide pose down', $resolved['prompt']);
        $this->assertStringContainsString('mobility- or accessibility-related positioning', $resolved['prompt']);
        $this->assertStringContainsString('large warm expressive eyes', $resolved['prompt']);
        $this->assertStringContainsString('unmistakably animated and non-photorealistic', $resolved['prompt']);
        $this->assertStringContainsString('Do not copy the reference character', $resolved['prompt']);
        $this->assertStringContainsString('Identity preservation has higher priority', $resolved['prompt']);
        $this->assertStringContainsString('top of the hair to the feet or paws', $resolved['prompt']);
        $this->assertStringContainsString('no text, no name, no logo', $resolved['prompt']);
        $this->assertStringNotContainsString('Disney style', $resolved['prompt']);
        $this->assertStringNotContainsString('Pixar', $resolved['prompt']);
        $this->assertStringNotContainsString('TreatPod', $resolved['prompt']);
    }

    public function test_hand_drawn_v5_uses_the_chibi_style_and_conditional_pose_fallback(): void
    {
        [$session] = $this->createArtworkSession('hand-drawn', ['orientation' => 'auto']);

        $resolved = app(GenerationPromptBuilder::class)->build($session);

        $this->assertSame('hand-drawn-v5', $resolved['key']);
        $this->assertSame(5, $resolved['version']);
        $this->assertSame('hand-drawn-v4', $resolved['input_references'][0]['key']);
        $this->assertSame('style_only', $resolved['input_references'][0]['role']);
        $this->assertFalse($resolved['output_requirements']['transparent_background']);
        $this->assertStringContainsString('handmade chibi children\'s-book character', $resolved['prompt']);
        $this->assertStringContainsString('oversized rounded head', $resolved['prompt']);
        $this->assertStringContainsString('translucent pastel watercolour washes', $resolved['prompt']);
        $this->assertStringContainsString('if IMAGE 1 clearly shows a complete, natural, usable body pose, preserve that pose and body position', $resolved['prompt']);
        $this->assertStringContainsString('do not infer a complex pose', $resolved['prompt']);
        $this->assertStringContainsString('front-facing neutral standing pose with arms relaxed at the sides', $resolved['prompt']);
        $this->assertStringContainsString('Do not preserve the customer photo\'s camera angle', $resolved['prompt']);
    }

    public function test_generation_freezes_effective_output_requirements(): void
    {
        [$session] = $this->createArtworkSession('storybook-cartoon', ['framing' => 'full_body', 'transparent_background' => true]);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'prompt-input', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $session->update(['current_upload_id' => $upload->id]);
        Queue::fake();

        $generation = app(RequestArtworkGeneration::class)->handle($session);

        $this->assertSame('full_body', $generation->parameters['output_requirements']['framing']);
        $this->assertTrue($generation->parameters['output_requirements']['transparent_background']);
        $this->assertSame('storybook-v5', $generation->prompt_key);
        $this->assertSame(5, $generation->prompt_version);
        $this->assertSame('style_only', $generation->parameters['input_references'][0]['role']);
    }

    public function test_an_active_generation_is_reused_instead_of_duplicated(): void
    {
        [$session] = $this->createArtworkSession('storybook-cartoon', []);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'duplicate-input', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64)]);
        $session->update(['current_upload_id' => $upload->id]);
        Queue::fake();

        $first = app(RequestArtworkGeneration::class)->handle($session);
        $second = app(RequestArtworkGeneration::class)->handle($session->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $session->generations()->count());
        Queue::assertPushed(\App\Jobs\GenerateArtwork::class, 1);
    }

    private function createArtworkSession(string $styleSlug, array $requirements): array
    {
        $product = Product::factory()->create(['artwork_requirements' => $requirements]);
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => $styleSlug, 'slug' => $styleSlug, 'prompt_key' => str_replace('-', '_', $styleSlug), 'prompt_version' => 2, 'is_active' => true]);
        $product->artworkStyles()->attach($style);

        return app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id]);
    }
}
