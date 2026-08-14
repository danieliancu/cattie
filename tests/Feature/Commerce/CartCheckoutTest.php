<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_checkout_prefills_email_and_links_order_to_customer(): void
    {
        [$session] = $this->approved();
        $user = User::factory()->create(['email' => 'customer@example.com', 'is_admin' => false]);
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->firstOrFail();
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))->assertOk()->assertSee('value="customer@example.com"', false);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'order-email@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'];
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();
        $order = $cart->fresh()->convertedOrder;
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('order-email@example.com', $order->email);
    }

    public function test_preview_can_be_added_directly_and_is_approved_in_the_same_action(): void
    {
        [$session, $variant] = $this->approved();
        $session->product->images()->create(['disk' => 'public', 'storage_key' => 'products/generic.png', 'role' => 'primary', 'is_product' => false, 'alt_text' => 'Generic product', 'is_active' => true, 'sort_order' => 0]);
        $session->product->images()->create(['product_variant_id' => $variant->id, 'disk' => 'public', 'storage_key' => 'products/selected-variant.png', 'role' => 'gallery', 'is_product' => true, 'alt_text' => 'Selected variant', 'is_active' => true, 'sort_order' => 1]);
        $session->approvedAsset?->update(['is_selected' => false, 'selected_at' => null]);
        $session->update(['status' => ArtworkSessionStatus::PreviewReady, 'current_generation_id' => $session->approvedAsset->generation_id, 'approved_generation_asset_id' => null, 'approved_at' => null]);

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('products.show', $session->product->slug))
            ->assertOk()->assertSee('Add to basket')->assertSee('Change artwork')->assertDontSee('Love it');
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id))
            ->assertRedirect(route('cart.index'));

        $this->assertSame(ArtworkSessionStatus::Approved, $session->fresh()->status);
        $this->assertDatabaseHas('cart_items', ['artwork_session_id' => $session->id]);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('cart.index'))
            ->assertOk()->assertSee('aria-label="1 items in basket"', false)
            ->assertSee('products/selected-variant.png', false)->assertDontSee('products/generic.png', false)
            ->assertSee('h-auto w-[100px]', false)->assertSee('ml-auto w-[100px] shrink-0 text-center text-base font-bold', false);
    }

    private function approved(string $token = 'owner-secret'): array
    {
        Storage::fake('local');
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price_minor' => 2499]);
        $variant->fulfilmentMappings()->create(['provider' => 'treatpod', 'provider_sku' => 'TEST-SKU', 'configuration' => [], 'is_active' => true]);
        $this->seed(ShippingMethodSeeder::class);
        $style = ArtworkStyle::query()->create(['name' => 'Storybook Cartoon', 'slug' => 'storybook-cartoon', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => []], $token);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'original', 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'storybook-v1', 'prompt_version' => 1, 'resolved_prompt' => 'safe', 'provider' => 'fake', 'model' => 'gpt-image-2', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'USD']);
        Storage::disk('local')->put('preview.webp', 'preview');
        $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'preview.webp', 'mime_type' => 'image/webp']);
        app(ApproveArtwork::class)->handle($session, $asset);

        return [$session->fresh(), $variant, $asset];
    }

    public function test_approved_artwork_add_is_authoritative_idempotent_and_guest_isolated(): void
    {
        [$session, $variant] = $this->approved();
        $url = route('artwork.cart', $session->public_id);
        $this->withCookie('cattie_guest_token', 'other')->post($url)->assertNotFound();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post($url, ['unit_price_minor' => 1])->assertRedirect(route('cart.index'));
        $this->withCookie('cattie_guest_token', 'owner-secret')->post($url)->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['artwork_session_id' => $session->id, 'unit_price_minor' => $variant->price_minor, 'quantity' => 1]);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('cart.index'))
            ->assertOk()->assertSee('Cartoon')->assertDontSee('Storybook Cartoon');
    }

    public function test_quantity_limits_and_price_refresh_are_server_authoritative(): void
    {
        [$session, $variant] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $item = Cart::query()->first()->items()->first();
        $this->withCookie('cattie_guest_token', 'owner-secret')->patch(route('cart.quantity', $item), ['quantity' => 11])->assertSessionHasErrors('quantity');
        $variant->update(['price_minor' => 2799]);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('cart.index'))->assertOk()->assertSee('£27.99');
        $this->assertSame(2799, $item->fresh()->unit_price_minor);
    }

    public function test_checkout_uses_admin_shipping_selection_and_freezes_its_order_snapshot(): void
    {
        [$session] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->firstOrFail();
        $standard = ShippingMethod::query()->where('code', 'royal_mail_48_tracked')->firstOrFail();
        $express = ShippingMethod::query()->where('code', 'royal_mail_24_tracked')->firstOrFail();

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('Royal Mail 48 Tracked')->assertSee('5–8 business days')
            ->assertSee('Royal Mail 24 Tracked')->assertSee('DPD')
            ->assertSee("shippingMethodId:'{$standard->id}'", false);

        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => $express->id, 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'];
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();
        $order = $cart->fresh()->convertedOrder;
        $this->assertNull($order->user_id);
        $this->assertSame($express->id, $order->shipping_method_id);
        $this->assertSame('ROYAL_MAIL_24_TRACKED', $order->shipping_method_snapshot['provider_service_code']);

        $express->update(['price_minor' => 999, 'name' => 'Changed later']);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('Royal Mail 24 Tracked')->assertSee('£4.13')->assertDontSee('Changed later');
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.payment', $order->number))
            ->assertOk()->assertSee('Royal Mail 24 Tracked')->assertSee('£4.13')
            ->assertSee('Cartoon')->assertDontSee('Storybook Cartoon');
        $this->assertSame(413, $order->fresh()->shipping_minor);
        $this->assertSame('Royal Mail 24 Tracked', $order->shipping_method_snapshot['name']);
    }

    public function test_checkout_rejects_a_shipping_method_for_another_provider(): void
    {
        [$session] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->firstOrFail();
        $foreign = ShippingMethod::query()->create(['provider' => 'prodigi', 'code' => 'foreign', 'name' => 'Other provider', 'provider_service_code' => 'OTHER', 'price_minor' => 100, 'currency' => 'GBP', 'country' => 'GB', 'estimated_business_days_min' => 1, 'estimated_business_days_max' => 2, 'is_active' => true]);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => $foreign->id, 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'];

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertSessionHasErrors('shipping_method_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changing_shipping_method_replaces_the_pending_order(): void
    {
        [$session] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->firstOrFail();
        $standard = ShippingMethod::query()->where('code', 'royal_mail_48_tracked')->firstOrFail();
        $express = ShippingMethod::query()->where('code', 'royal_mail_24_tracked')->firstOrFail();
        $customer = ['pricing_hash' => $cart->pricing_hash, 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'];

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), [
            ...$customer, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => $standard->id,
        ])->assertRedirect();
        $firstOrder = $cart->fresh()->convertedOrder;

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), [
            ...$customer, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => $express->id,
        ])->assertRedirect();
        $secondOrder = $cart->fresh()->convertedOrder;

        $this->assertNotSame($firstOrder->id, $secondOrder->id);
        $this->assertSame(OrderStatus::Cancelled, $firstOrder->fresh()->status);
        $this->assertSame($express->id, $secondOrder->shipping_method_id);
        $this->assertSame(413, $secondOrder->shipping_method_snapshot['price_minor']);
    }

    public function test_checkout_order_is_immutable_but_editing_reopens_the_live_basket(): void
    {
        [$session, $variant, $asset] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->first();
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => ' Mia ', 'last_name' => 'Smith', 'email' => 'MIA@EXAMPLE.COM', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'sw1a 1aa', 'country' => 'GB'];
        $response = $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload);
        $order = $cart->fresh()->convertedOrder;
        $response->assertRedirect(route('checkout.payment', $order->number));
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertFalse($order->is_payable);
        $this->assertNull($order->total_minor);
        $this->assertSame('mia@example.com', $order->email);
        $this->assertSame('SW1A 1AA', $order->shipping_address['postcode']);
        $this->assertSame('active', $cart->fresh()->status);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('cart.index'))
            ->assertOk()->assertSee($session->product->name)->assertSee('Continue to checkout')->assertDontSee('Continue payment')->assertSee('Change artwork')->assertSee('Remove')->assertDontSee('Checkout in progress');
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('Where should we send it?')->assertSee('Order summary');
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'generation_asset_id' => $asset->id, 'unit_price_minor' => $variant->price_minor]);
        $item = $cart->items()->first();
        $this->withCookie('cattie_guest_token', 'owner-secret')->patch(route('cart.quantity', $item), ['quantity' => 2])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertNull($cart->fresh()->converted_order_id);
        $this->assertSame(2, $item->fresh()->quantity);

        $nextPayload = [...$payload, 'pricing_hash' => $cart->fresh()->pricing_hash, 'checkout_idempotency_key' => fake()->uuid()];
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $nextPayload)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $variant->update(['price_minor' => 9999]);
        $this->assertSame(2499, $order->items()->first()->unit_price_minor);
        $this->withCookie('cattie_guest_token', 'other')->get(route('checkout.payment', $order->number))->assertNotFound();
    }

    public function test_price_change_interrupts_checkout_and_linked_expired_artwork_survives_purge(): void
    {
        [$session, $variant] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $cart = Cart::query()->first();
        $oldHash = $cart->pricing_hash;
        $variant->update(['price_minor' => 2899]);
        $payload = ['pricing_hash' => $oldHash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'];
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertSessionHasErrors('pricing_hash');
        $this->assertDatabaseCount('orders', 0);
        DB::table('artwork_sessions')->where('id', $session->id)->update(['status' => 'failed', 'expires_at' => now()->subDay()]);
        $this->artisan('artwork:purge-expired')->assertSuccessful();
        $this->assertDatabaseHas('artwork_sessions', ['id' => $session->id]);
    }

    public function test_cart_hides_approved_session_from_product_until_change_artwork_removes_item(): void
    {
        [$session] = $this->approved();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id));
        $item = Cart::query()->firstOrFail()->items()->firstOrFail();

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('products.show', $session->product->slug))->assertOk()->assertSee('Choose your character')->assertSee('Preview')->assertDontSee('This is the one.');
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('cart.change-artwork', $item))->assertRedirect(route('products.show', $session->product->slug));
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        $this->assertSame(ArtworkSessionStatus::PreviewReady, $session->fresh()->status);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('products.show', $session->product->slug))->assertOk()->assertSee('Your artwork is ready.')->assertDontSee('This is the one.');
    }
}
