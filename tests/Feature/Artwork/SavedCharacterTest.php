<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\ReuseSavedCharacter;
use App\Enums\ArtworkProcessingStage;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavedCharacterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_saves_exact_character_idempotently_and_preview_is_private(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => false]);
        [$session, $asset] = $this->readySession($user);

        $this->actingAs($user)->postJson(route('artwork.save-character', $session->public_id))->assertOk()->assertJsonPath('name', 'Anna');
        $this->actingAs($user)->postJson(route('artwork.save-character', $session->public_id))->assertOk();

        $this->assertDatabaseCount('saved_characters', 1);
        $character = $user->savedCharacters()->firstOrFail();
        $this->assertSame(hash('sha256', Storage::disk('local')->get($asset->storage_key)), $character->sha256);
        Storage::disk('local')->assertExists($character->storage_key);
        Storage::disk('local')->assertExists($character->preview_storage_key);
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('account.characters.preview', $character))->assertNotFound();
        $this->actingAs($user)->get(route('account.characters.preview', $character))->assertOk()->assertHeader('content-type', 'image/webp');
    }

    public function test_guest_save_survives_registration_and_returns_to_product(): void
    {
        Storage::fake('local');
        [$session] = $this->readySession();
        $token = 'guest-save-token';
        $session->update(['access_token_hash' => hash('sha256', $token)]);
        $this->withCookie('cattie_guest_token', $token)->post(route('artwork.save-character', $session->public_id), [], ['Accept' => 'application/json'])
            ->assertStatus(202)->assertJsonPath('redirect_url', route('register'));
        $response = $this->withCookie('cattie_guest_token', $token)->post(route('register.store'), [
            'email' => 'anna@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('products.show', $session->product->slug));
        $this->assertDatabaseCount('saved_characters', 1);
        $this->assertNotNull($session->fresh()->user_id);
    }

    public function test_saved_character_reuse_creates_zero_cost_local_generation_without_upload(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => false]);
        [$source] = $this->readySession($user);
        $this->actingAs($user)->postJson(route('artwork.save-character', $source->public_id))->assertOk();
        $character = $user->savedCharacters()->firstOrFail();
        [$target] = $this->readySession($user, false, $source->product, $source->artworkStyle);
        $target->update(['status' => ArtworkSessionStatus::AwaitingUpload, 'current_generation_id' => null]);

        $generation = app(ReuseSavedCharacter::class)->handle($target->load(['product.designTemplate', 'product.artworkStyles']), $character);

        $this->assertNull($generation->upload_id);
        $this->assertSame($character->id, $generation->saved_character_id);
        $this->assertSame(0, $generation->cost_micros);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('saved-character', $generation->provider);
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $target->fresh()->status);
        $this->assertEqualsCanonicalizing(['composition_source', 'web_preview'], $generation->assets()->pluck('kind')->all());
    }

    public function test_customer_can_start_another_product_with_saved_character_and_no_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => false]);
        [$source] = $this->readySession($user);
        $this->actingAs($user)->postJson(route('artwork.save-character', $source->public_id));
        $character = $user->savedCharacters()->firstOrFail();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $product->artworkStyles()->attach($character->artwork_style_id);

        $this->actingAs($user)->post(route('artwork.start', $product->slug), [
            'variant_id' => $variant->id,
            'artwork_style_id' => $character->artwork_style_id,
            'saved_character_id' => $character->id,
        ])->assertRedirect(route('products.show', $product->slug));

        $target = ArtworkSession::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $target->status);
        $this->assertSame($character->id, $target->currentGeneration->saved_character_id);
        $this->assertNull($target->currentGeneration->upload_id);
    }

    public function test_deleting_library_copy_does_not_delete_reused_generation_assets(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => false]);
        [$source] = $this->readySession($user);
        $this->actingAs($user)->postJson(route('artwork.save-character', $source->public_id));
        $character = $user->savedCharacters()->firstOrFail();
        [$target] = $this->readySession($user, false, $source->product, $source->artworkStyle);
        $generation = app(ReuseSavedCharacter::class)->handle($target->load(['product.designTemplate', 'product.artworkStyles']), $character);
        $keys = $generation->assets()->pluck('storage_key')->all();

        $this->actingAs($user)->delete(route('account.characters.destroy', $character))->assertRedirect(route('account.characters.index'));

        $this->assertDatabaseMissing('saved_characters', ['id' => $character->id]);
        $this->assertNull($generation->fresh()->saved_character_id);
        foreach ($keys as $key) {
            Storage::disk('local')->assertExists($key);
        }
    }

    public function test_artwork_retention_removes_source_session_but_preserves_saved_character(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => false]);
        [$session] = $this->readySession($user);
        $this->actingAs($user)->postJson(route('artwork.save-character', $session->public_id));
        $character = $user->savedCharacters()->firstOrFail();
        $session->update(['expires_at' => now()->subMinute()]);

        $this->artisan('artwork:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('artwork_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('saved_characters', ['id' => $character->id, 'source_generation_asset_id' => null]);
        Storage::disk('local')->assertExists($character->storage_key);
        Storage::disk('local')->assertExists($character->preview_storage_key);
    }

    private function readySession(?User $user = null, bool $withGeneration = true, ?Product $product = null, ?ArtworkStyle $style = null): array
    {
        $product ??= Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $style ??= ArtworkStyle::query()->firstOrCreate(['slug' => 'storybook-cartoon'], ['name' => 'Storybook Cartoon', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->syncWithoutDetaching([$style->id]);
        $session = ArtworkSession::query()->create([
            'public_id' => (string) Str::ulid(), 'access_token_hash' => hash('sha256', 'owner-token'), 'user_id' => $user?->id,
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id,
            'personalisation_snapshot' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => 'Anna']],
            'status' => ArtworkSessionStatus::PreviewReady, 'processing_stage' => ArtworkProcessingStage::Ready, 'expires_at' => now()->addDay(),
        ]);
        if (! $withGeneration) {
            return [$session, null];
        }
        $generation = $session->generations()->create([
            'upload_id' => null, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id,
            'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake',
            'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP', 'completed_at' => now(),
        ]);
        [$png, $webp] = $this->images();
        $base = "artwork-sessions/{$session->public_id}/test";
        Storage::disk('local')->put("{$base}.png", $png);
        Storage::disk('local')->put("{$base}.webp", $webp);
        $asset = $generation->assets()->create(['kind' => 'composition_source', 'disk' => 'local', 'storage_key' => "{$base}.png", 'mime_type' => 'image/png', 'size_bytes' => strlen($png), 'width' => 20, 'height' => 30]);
        $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => "{$base}.webp", 'mime_type' => 'image/webp', 'size_bytes' => strlen($webp), 'width' => 20, 'height' => 30]);
        $session->update(['current_generation_id' => $generation->id]);

        return [$session->fresh(), $asset];
    }

    private function images(): array
    {
        $image = imagecreatetruecolor(20, 30);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, 10, 15, 12, 24, imagecolorallocatealpha($image, 200, 100, 80, 0));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        ob_start();
        imagewebp($image);
        $webp = ob_get_clean();
        imagedestroy($image);

        return [$png, $webp];
    }
}
