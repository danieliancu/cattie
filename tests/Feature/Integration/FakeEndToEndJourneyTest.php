<?php

namespace Tests\Feature\Integration;

use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Enums\OrderStatus;
use App\Models\AnalyticsEvent;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FakeEndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_fake_customer_journey_reaches_paid_without_external_providers(): void
    {
        Storage::fake('local');
        config(['queue.default' => 'sync', 'artwork.provider' => 'fake', 'artwork.fake_failure' => false, 'payments.provider' => 'fake', 'payments.fake.enabled' => true]);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price_minor' => 2499]);
        $style = ArtworkStyle::query()->create(['name' => 'Storybook Cartoon', 'slug' => 'storybook-cartoon', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => []], 'journey-owner');
        $this->withCookie('cattie_guest_token', 'journey-owner');

        $this->post(route('artwork.upload', $session->public_id), ['photo' => UploadedFile::fake()->image('portrait.jpg', 800, 1000)])->assertRedirect(route('artwork.show', $session->public_id));
        $session->refresh();
        $generation = $session->currentGeneration;
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $session->status);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertEqualsCanonicalizing(['provider_original', 'web_preview'], $generation->assets->pluck('kind')->all());
        $preview = $generation->assets->firstWhere('kind', 'web_preview');
        $this->withCookie('cattie_guest_token', 'journey-owner')->post(route('artwork.approve', $session->public_id), ['asset_id' => $preview->id])->assertRedirect();
        $this->withCookie('cattie_guest_token', 'journey-owner')->post(route('artwork.cart', $session->public_id), ['unit_price_minor' => 1])->assertRedirect(route('cart.index'));
        $cart = Cart::query()->firstOrFail();
        $this->withCookie('cattie_guest_token', 'journey-owner')->get(route('checkout.show'))->assertOk();
        $checkout = ['pricing_hash' => $cart->fresh()->pricing_hash, 'checkout_idempotency_key' => (string) Str::uuid(), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'];
        $this->withCookie('cattie_guest_token', 'journey-owner')->post(route('checkout.store'), $checkout)->assertRedirect();
        $order = $cart->fresh()->convertedOrder;
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->withCookie('cattie_guest_token', 'journey-owner')->get(route('checkout.payment', $order->number))->assertOk();
        $this->withCookie('cattie_guest_token', 'journey-owner')->post(route('checkout.pay', $order->number), ['idempotency_key' => (string) Str::uuid(), 'scenario' => 'success', 'amount_minor' => 1])->assertRedirect(route('orders.confirmation', $order->number));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        foreach (['uploads' => 1, 'generations' => 1, 'generation_assets' => 2, 'cart_items' => 1, 'orders' => 1, 'order_items' => 1, 'payments' => 1] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($preview->id, $order->items()->first()->generation_asset_id);
        $expected = ['artwork_session_started', 'photo_uploaded', 'generation_requested', 'generation_succeeded', 'artwork_approved', 'add_to_cart', 'checkout_started', 'order_created', 'payment_started', 'payment_succeeded', 'order_paid'];
        $this->assertEmpty(array_diff($expected, AnalyticsEvent::query()->pluck('name')->all()));
    }
}
