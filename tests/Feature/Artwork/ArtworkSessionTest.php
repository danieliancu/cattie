<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Jobs\NormaliseArtworkUpload;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArtworkSessionTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(): array
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => 'Storybook', 'slug' => 'storybook-cartoon', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        $product->personalisationFields()->create(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true, 'validation_rules' => ['max' => 12]]);

        return [$product, $variant, $style];
    }

    public function test_name_is_limited_to_twelve_characters_including_spaces(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, [
            'variant_id' => $variant->id,
            'artwork_style_id' => $style->id,
            'personalisation' => ['name' => 'Mary Ann Doe'],
        ]);
        $this->assertSame('Mary Ann Doe', $session->personalisation_snapshot[0]['value']);

        $this->expectException(ValidationException::class);
        app(StartArtworkSession::class)->handle($product, [
            'variant_id' => $variant->id,
            'artwork_style_id' => $style->id,
            'personalisation' => ['name' => 'Mary Ann Doe!'],
        ]);
    }

    public function test_valid_guest_session_starts_and_invalid_selection_is_rejected(): void
    {
        [$product,$variant,$style] = $this->catalogue();
        [$session,$token] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']]);
        $this->assertSame(ArtworkSessionStatus::AwaitingUpload, $session->status);
        $this->assertSame('Mia', $session->personalisation_snapshot[0]['value']);
        $this->assertSame(hash('sha256', $token), $session->access_token_hash);
        $this->expectException(ValidationException::class);
        app(StartArtworkSession::class)->handle($product, ['variant_id' => 'bad', 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']]);
    }

    public function test_guest_cannot_access_another_guests_session(): void
    {
        [$product,$variant,$style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']], 'owner-secret');
        $this->withCookie('cattie_guest_token', 'different-secret')->get(route('artwork.show', $session->public_id))->assertNotFound();
    }

    public function test_valid_upload_is_private_and_dispatches_normalisation(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$product,$variant,$style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']], 'owner-secret');
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.upload', $session->public_id), ['photo' => UploadedFile::fake()->image('photo.jpg', 800, 900)])->assertRedirect();
        $upload = $session->fresh()->currentUpload;
        $this->assertSame('local', $upload->disk);
        Storage::disk('local')->assertExists($upload->storage_key);
        Queue::assertPushed(NormaliseArtworkUpload::class);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('artwork.show', $session->public_id))->assertDontSee($upload->storage_key);
    }

    public function test_repeated_upload_submission_redirects_to_existing_progress_instead_of_conflict(): void
    {
        [$product,$variant,$style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']], 'owner-secret');
        $session->update(['status' => ArtworkSessionStatus::PreviewReady]);

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.upload', $session->public_id), [
            'photo' => UploadedFile::fake()->image('duplicate.jpg', 800, 900),
        ])->assertRedirect(route('products.show', $session->product->slug));
        $this->assertDatabaseCount('uploads', 0);
    }

    public function test_regeneration_is_immutable_and_has_no_generation_limit(): void
    {
        [$product,$variant,$style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']]);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'x', 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $session->update(['current_upload_id' => $upload->id]);
        Queue::fake();
        $first = app(RequestArtworkGeneration::class)->handle($session);
        $second = app(RequestArtworkGeneration::class)->handle($session->fresh(), true);
        $third = app(RequestArtworkGeneration::class)->handle($session->fresh(), true);
        app(RequestArtworkGeneration::class)->handle($session->fresh(), true);
        app(RequestArtworkGeneration::class)->handle($session->fresh(), true);
        $this->assertSame($first->id, $second->parent_generation_id);
        $this->assertSame(3, $third->parameters['generation_sequence']);
        $this->assertDatabaseCount('generations', 5);
    }

    public function test_approval_rejects_cross_session_asset(): void
    {
        [$product,$variant,$style] = $this->catalogue();
        [$one] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Mia']]);
        [$two] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Leo']]);
        $upload = $two->uploads()->create(['disk' => 'local', 'storage_key' => 'x', 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64)]);
        $generation = $two->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'storybook-v1', 'prompt_version' => 1, 'resolved_prompt' => 'safe', 'provider' => 'fake', 'model' => 'gpt-image-2', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'USD']);
        $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'preview', 'mime_type' => 'image/png']);
        $this->expectException(ValidationException::class);
        app(ApproveArtwork::class)->handle($one, $asset);
    }
}
