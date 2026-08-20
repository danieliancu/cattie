<?php

namespace Tests\Feature\Artwork;

use App\Contracts\PhotoModerator;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PhotoModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkModerationTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(): array
    {
        $product = Product::factory()->create(['slug' => 'cattie-water-bottle', 'artwork_requirements' => ['aspect_ratio' => '4:5', 'transparent_background' => true]]);
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => 'Storybook', 'slug' => 'storybook-'.uniqid(), 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);

        return [$product, $variant, $style];
    }

    private function rejectWith(string $reason): void
    {
        $this->instance(PhotoModerator::class, new class($reason) implements PhotoModerator
        {
            public function __construct(private string $reason) {}

            public function moderate(string $absolutePath, string $mimeType): PhotoModerationResult
            {
                return PhotoModerationResult::rejected($this->reason);
            }
        });
    }

    public function test_photo_without_a_subject_is_rejected_with_a_polite_modal_and_no_session(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->rejectWith(PhotoModerationResult::NO_SUBJECT);
        [$product, $variant, $style] = $this->catalogue();

        $this->withCookie('cattie_guest_token', 'mod-owner')->post(route('artwork.start', $product->slug), [
            'variant_id' => $variant->id, 'artwork_style_id' => $style->id,
            'photo' => UploadedFile::fake()->image('scenery.jpg', 800, 900),
        ])->assertRedirect(route('products.show', $product->slug))->assertSessionHas('moderation_reason', 'no_subject');

        $this->assertDatabaseCount('artwork_sessions', 0);
        $this->withCookie('cattie_guest_token', 'mod-owner')->get(route('products.show', $product->slug))
            ->assertOk()->assertSee("We couldn't spot a face or a pet");
    }

    public function test_unsafe_photo_shows_the_content_guidelines_modal(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->rejectWith(PhotoModerationResult::UNSAFE);
        [$product, $variant, $style] = $this->catalogue();

        $this->withCookie('cattie_guest_token', 'mod-owner')->post(route('artwork.start', $product->slug), [
            'variant_id' => $variant->id, 'artwork_style_id' => $style->id,
            'photo' => UploadedFile::fake()->image('blocked.jpg', 800, 900),
        ])->assertSessionHas('moderation_reason', 'unsafe');

        $this->withCookie('cattie_guest_token', 'mod-owner')->get(route('products.show', $product->slug))
            ->assertOk()->assertSee("This photo can't be used");
    }

    private function openAiModerator(): PhotoModerator
    {
        config(['artwork.moderation.provider' => 'openai', 'artwork.openai.api_key' => 'test-key', 'artwork.openai.base_url' => 'https://api.openai.com/v1']);
        $moderator = app(PhotoModerator::class);
        $this->assertInstanceOf(\App\Services\OpenAiPhotoModerator::class, $moderator);

        return $moderator;
    }

    private function tempImage(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($tmp, 'binary-image-bytes');

        return $tmp;
    }

    public function test_openai_moderator_rejects_photos_flagged_as_unsafe(): void
    {
        Http::fake([
            '*/moderations' => Http::response(['results' => [['flagged' => true]]]),
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"person":true,"pet":false}']]]]),
        ]);
        $result = $this->openAiModerator()->moderate($tmp = $this->tempImage(), 'image/jpeg');
        @unlink($tmp);

        $this->assertSame(PhotoModerationResult::UNSAFE, $result->reason);
    }

    public function test_openai_moderator_rejects_photos_with_no_person_or_pet(): void
    {
        Http::fake([
            '*/moderations' => Http::response(['results' => [['flagged' => false]]]),
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"person":false,"pet":false}']]]]),
        ]);
        $result = $this->openAiModerator()->moderate($tmp = $this->tempImage(), 'image/jpeg');
        @unlink($tmp);

        $this->assertSame(PhotoModerationResult::NO_SUBJECT, $result->reason);
    }

    public function test_openai_moderator_allows_a_safe_photo_with_a_subject(): void
    {
        Http::fake([
            '*/moderations' => Http::response(['results' => [['flagged' => false]]]),
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"person":true,"pet":false}']]]]),
        ]);
        $result = $this->openAiModerator()->moderate($tmp = $this->tempImage(), 'image/jpeg');
        @unlink($tmp);

        $this->assertTrue($result->allowed);
    }

    public function test_openai_moderator_fails_open_without_an_api_key(): void
    {
        config(['artwork.moderation.provider' => 'openai', 'artwork.openai.api_key' => '']);
        Http::fake();
        $tmp = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($tmp, 'bytes');

        $this->assertTrue(app(PhotoModerator::class)->moderate($tmp, 'image/jpeg')->allowed);
        Http::assertNothingSent();

        @unlink($tmp);
    }
}
