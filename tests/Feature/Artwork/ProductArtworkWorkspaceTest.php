<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\ResolveResumableArtworkSession;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\ArtworkProcessingStage;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductDesignTemplate;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductArtworkWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_redirects_to_product_and_new_session_is_rendered_inline(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$product, $variant, $style] = $this->catalogue();
        $response = $this->withCookie('cattie_guest_token', 'workspace-owner')->post(route('artwork.start', $product->slug), [
            'variant_id' => $variant->id,
            'artwork_style_id' => $style->id,
            'photo' => UploadedFile::fake()->image('portrait.jpg', 800, 900),
        ]);

        $response->assertRedirect(route('products.show', $product->slug));
        $session = ArtworkSession::query()->firstOrFail();
        $page = $this->withCookie('cattie_guest_token', 'workspace-owner')->get(route('products.show', $product->slug));
        $page->assertOk()->assertSee('Creating your artwork')->assertSee('Creating your illustration')->assertSee('Removing the background')->assertSee('Preparing your preview')->assertSee('Your artwork is ready')->assertSee('View Your Design')->assertSee('role="dialog"', false)->assertSee($session->public_id);
        $page->assertSee('artwork-progress-overlay', false)->assertSee('bg-cover bg-center', false)->assertSee('role="progressbar"', false);
        $page->assertSee(route('artwork.cancel', $session->public_id))->assertSee('Cancel artwork creation');
        $this->assertSame(ArtworkProcessingStage::PreparingPhoto, $session->fresh()->processing_stage);
        $this->assertStringContainsString('private', $page->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
    }

    public function test_customer_can_force_cancel_an_active_conversion(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'cancel-owner');
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'cancel-input', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => str_repeat('c', 64)]);
        $generation = $session->generations()->create([
            'upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id,
            'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake',
            'status' => GenerationStatus::Processing, 'cost_currency' => 'GBP',
        ]);
        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::Generating]);

        $this->withCookie('cattie_guest_token', 'cancel-owner')->post(route('artwork.cancel', $session->public_id))
            ->assertRedirect(route('products.show', $product->slug));

        $this->assertSame(GenerationStatus::Cancelled, $generation->fresh()->status);
        $this->assertSame(ArtworkSessionStatus::AwaitingUpload, $session->fresh()->status);
    }

    public function test_bottle_product_starts_with_colour_name_photo_and_preview_in_one_form(): void
    {
        [$product] = $this->catalogue();
        $template = ProductDesignTemplate::query()->create([
            'key' => 'bottle-wrap-v1', 'version' => 4,
            'definition_path' => 'bottle-wrap-v1/template.json',
        ]);
        $product->update(['product_design_template_id' => $template->id]);
        $product->personalisationFields()->create([
            'key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true,
            'validation_rules' => ['max' => 12], 'sort_order' => 1,
        ]);

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('Search products')->assertSee('Track Order')->assertSee('Sign in')->assertSee('mobile-navigation')
            ->assertSee('Bottle colour')->assertSee('Name')->assertSee('Choose your character')->assertSee('+ Upload')->assertSee('Preview')
            ->assertSee('Made')->assertSee('For You')->assertSee('Secure')->assertSee('Privacy')->assertSee('Shipping &amp; Returns', false)
            ->assertSee('personalised items are made to order')->assertSee('Made and printed in the UK')->assertSee('3–5 working days')
            ->assertSee('£3.50')->assertSee('Royal Mail 48 Tracked')->assertSee('Royal Mail 24 Tracked')->assertSee('DPD')
            ->assertDontSee('99 Day')->assertDontSee('Delivery Time = Processing Time')
            ->assertSee('min-h-24', false)
            ->assertSee('bottle-colour-options grid grid-cols-4', false)
            ->assertSee("12 - nameValue.length) + ' characters left'", false)
            ->assertSee(':disabled="submitting || !variantReady() || !photoReady() || !nameValue.trim()"', false)
            ->assertSee('@submit="submitting = true"', false)
            ->assertSee('Starting preview', false)
            ->assertSee('@change="selectPhoto($event)"', false)
            ->assertSee('name="photo"', false)->assertSee('enctype="multipart/form-data"', false)
            ->assertDontSee('Choose your artwork style');
    }

    public function test_change_artwork_preserves_name_and_photo_until_the_photo_is_removed(): void
    {
        Storage::fake('local');
        [$product, $variant, $style] = $this->catalogue();
        $product->personalisationFields()->create(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true, 'validation_rules' => ['max' => 30]]);
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Maria']], 'change-owner');
        Storage::disk('local')->put('originals/maria.jpg', 'photo');
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'originals/maria.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 5, 'sha256' => str_repeat('a', 64)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'safe', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        Storage::disk('local')->put('previews/maria.webp', 'illustration');
        $preview = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'previews/maria.webp', 'mime_type' => 'image/webp']);
        $session->update(['current_upload_id' => $upload->id, 'current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        $this->withCookie('cattie_guest_token', 'change-owner')->post(route('artwork.change', $session->public_id))
            ->assertRedirect(route('products.show', $product->slug));

        $this->assertSame(ArtworkSessionStatus::AwaitingUpload, $session->fresh()->status);
        $this->withCookie('cattie_guest_token', 'change-owner')->post(route('artwork.change', $session->public_id))
            ->assertRedirect(route('products.show', $product->slug));
        $this->withCookie('cattie_guest_token', 'change-owner')->get(route('products.show', $product->slug))
            ->assertOk()->assertSee('value="Maria"', false)->assertSee('Current character')
            ->assertSee(route('artwork.assets', [$session->public_id, $preview]), false)
            ->assertSee('Save character')->assertSee('hasExistingPhoto:true', false)
            ->assertSee('Choose your character')->assertDontSee('Your artwork is ready');
        $this->withCookie('cattie_guest_token', 'change-owner')->post(route('artwork.upload', $session->public_id), [
            'variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Maria'],
        ])->assertRedirect(route('products.show', $product->slug));
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $session->fresh()->status);
        $this->assertSame(1, $session->generations()->count());
    }

    public function test_resolver_uses_latest_owned_unexpired_eligible_session_for_product(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$old] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'owner-token');
        $old->update(['created_at' => now()->subMinute()]);
        [$new] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'owner-token');
        [$otherGuest] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'intruder-token');
        [$expired] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'owner-token');
        $expired->update(['expires_at' => now()->subSecond(), 'created_at' => now()->addMinute()]);

        $request = Request::create(route('products.show', $product->slug));
        $request->cookies->set('cattie_guest_token', 'owner-token');
        $resolved = app(ResolveResumableArtworkSession::class)->handle($product, $request);
        $this->assertSame($new->id, $resolved?->id);
        $this->assertNotSame($old->id, $resolved?->id);
        $this->assertNotSame($otherGuest->id, $resolved?->id);
    }

    public function test_resolver_handles_approved_sessions_according_to_cart_relationship(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'approved-owner');
        $session->update(['status' => ArtworkSessionStatus::Approved]);

        $request = Request::create(route('products.show', $product->slug));
        $request->cookies->set('cattie_guest_token', 'approved-owner');
        $this->assertSame($session->id, app(ResolveResumableArtworkSession::class)->handle($product, $request)?->id);

        $cart = Cart::query()->create(['status' => 'active', 'currency' => 'GBP']);
        $session->cartItems()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'personalisation' => [], 'quantity' => 1, 'unit_price_minor' => $variant->price_minor, 'currency' => 'GBP']);
        $this->assertNull(app(ResolveResumableArtworkSession::class)->handle($product, $request));
    }

    public function test_another_guest_sees_initial_configurator_and_legacy_route_is_private(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'owner-token');

        $this->withCookie('cattie_guest_token', 'intruder-token')->get(route('products.show', $product->slug))->assertOk()->assertSee('Preview')->assertSee('Choose your character')->assertDontSee('Creating your artwork');
        $this->withCookie('cattie_guest_token', 'intruder-token')->get(route('artwork.show', $session->public_id))->assertNotFound();
        $this->withCookie('cattie_guest_token', 'owner-token')->get(route('artwork.show', $session->public_id))->assertRedirect(route('products.show', $product->slug));
    }

    public function test_private_actions_and_polling_keep_product_as_the_browser_workspace(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'action-owner');
        Storage::disk('local')->put('workspace-source.jpg', 'original-photo');
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'workspace-source.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 14, 'sha256' => str_repeat('a', 64)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'safe', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        Storage::disk('local')->put('workspace-preview.webp', 'preview');
        $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'workspace-preview.webp', 'mime_type' => 'image/webp']);
        $session->update(['current_upload_id' => $upload->id, 'current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady, 'processing_stage' => ArtworkProcessingStage::Ready]);
        $productUrl = route('products.show', $product->slug);

        $this->withCookie('cattie_guest_token', 'intruder')->get(route('artwork.status', $session->public_id))->assertNotFound();
        $this->withCookie('cattie_guest_token', 'action-owner')->get(route('artwork.status', $session->public_id))->assertOk()
            ->assertJsonPath('status', 'preview_ready')->assertJsonPath('stage', 'ready')->assertJsonPath('progress', 100)->assertJsonPath('view_url', $productUrl);
        $this->withCookie('cattie_guest_token', 'intruder')->get(route('artwork.original', $session->public_id))->assertNotFound();
        $this->withCookie('cattie_guest_token', 'action-owner')->get(route('artwork.original', $session->public_id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->withCookie('cattie_guest_token', 'action-owner')->post(route('artwork.regenerate', $session->public_id))->assertRedirect($productUrl);
        $this->withCookie('cattie_guest_token', 'action-owner')->post(route('artwork.approve', $session->public_id), ['asset_id' => $asset->id])->assertRedirect($productUrl);
        $this->withCookie('cattie_guest_token', 'action-owner')->post(route('artwork.change', $session->public_id))->assertRedirect($productUrl);
    }

    public function test_polling_maps_real_processing_stages_to_progress_below_one_hundred(): void
    {
        [$product, $variant, $style] = $this->catalogue();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id], 'progress-owner');
        $session->update(['status' => ArtworkSessionStatus::Generating]);

        foreach ([
            ArtworkProcessingStage::PreparingPhoto->value => 10,
            ArtworkProcessingStage::CreatingIllustration->value => 30,
            ArtworkProcessingStage::RemovingBackground->value => 55,
            ArtworkProcessingStage::PreparingPreview->value => 80,
        ] as $stage => $progress) {
            $session->update(['processing_stage' => $stage]);
            $this->withCookie('cattie_guest_token', 'progress-owner')->get(route('artwork.status', $session->public_id))
                ->assertOk()->assertJsonPath('stage', $stage)->assertJsonPath('progress', $progress)->assertJsonPath('view_url', null);
            $this->assertLessThan(100, $progress);
        }
    }

    private function catalogue(): array
    {
        $product = Product::factory()->create(['slug' => 'cattie-water-bottle']);
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => 'Storybook', 'slug' => 'storybook-'.uniqid(), 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);

        return [$product, $variant, $style];
    }
}
