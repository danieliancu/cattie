<?php

namespace Tests\Feature\Commerce;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\GenerationStatus;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\CustomerProfile;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_show_prefills_from_customer_profile_when_no_old_input_or_pending_order(): void
    {
        [, $user] = $this->cartWithUser();
        $this->profile($user, ['first_name' => 'Mia', 'last_name' => 'Smith', 'phone' => '07700900000'], [
            'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA',
        ]);

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('value="Mia"', false)->assertSee('value="Smith"', false)
            ->assertSee('value="1 High Street"', false)->assertSee('value="London"', false)
            ->assertSee('value="SW1A 1AA"', false)->assertSee('Using your saved details');
    }

    public function test_checkout_show_old_input_takes_priority_over_profile_defaults(): void
    {
        [, $user] = $this->cartWithUser();
        $this->profile($user, ['first_name' => 'Mia'], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')
            ->withSession(['_old_input' => ['first_name' => 'Temporary']])
            ->get(route('checkout.show'))
            ->assertOk()->assertSee('value="Temporary"', false)->assertDontSee('value="Mia"', false);
    }

    public function test_checkout_show_pending_order_snapshot_takes_priority_over_profile_defaults_on_revisit(): void
    {
        [$cart, $user] = $this->cartWithUser();
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '12 Grandma Road', 'city' => 'Manchester', 'postcode' => 'M1 1AE', 'country' => 'GB'];
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();

        $this->actingAs($user)->patchJson(route('account.details.update'), [
            'address_line_1' => '50 New Road', 'city' => 'Bristol', 'postcode' => 'BS1 1AA',
        ])->assertOk();

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('value="12 Grandma Road"', false)->assertSee('value="Manchester"', false)
            ->assertDontSee('value="50 New Road"', false)->assertDontSee('value="Bristol"', false);
    }

    public function test_checkout_show_falls_back_to_user_email_when_no_profile_exists(): void
    {
        [, $user] = $this->cartWithUser('nobody@example.com');

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('value="nobody@example.com"', false)->assertSee('value=""', false);
    }

    public function test_guest_checkout_prefill_is_unaffected_by_customer_profile_logic(): void
    {
        [$cart] = $this->cart();

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertDontSee('Using your saved details');
    }

    public function test_checkout_shows_save_default_address_checkbox_when_submitted_address_differs_from_profile_default(): void
    {
        [$cart, $user] = $this->cartWithUser();
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '12 Grandma Road', 'city' => 'Manchester', 'postcode' => 'M1 1AE', 'country' => 'GB'];
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertSee('name="save_default_address"', false);
    }

    public function test_checkout_hides_save_default_address_checkbox_when_address_already_matches_profile_default(): void
    {
        [, $user] = $this->cartWithUser();
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))
            ->assertOk()->assertDontSee('name="save_default_address"', false);
    }

    public function test_checking_save_default_address_updates_customer_profile_after_successful_checkout(): void
    {
        [$cart, $user] = $this->cartWithUser();
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '12 Grandma Road', 'city' => 'Manchester', 'postcode' => 'M1 1AE', 'country' => 'GB', 'save_default_address' => '1'];

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();

        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('12 Grandma Road', $profile->default_shipping_address['address_line_1']);
        $this->assertSame('Manchester', $profile->default_shipping_address['city']);
    }

    public function test_leaving_save_default_address_unchecked_does_not_mutate_customer_profile(): void
    {
        [$cart, $user] = $this->cartWithUser();
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '12 Grandma Road', 'city' => 'Manchester', 'postcode' => 'M1 1AE', 'country' => 'GB'];

        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();

        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('1 High Street', $profile->default_shipping_address['address_line_1']);
        $order = $cart->fresh()->convertedOrder;
        $this->assertSame('12 Grandma Road', $order->shipping_address['address_line_1']);
    }

    public function test_order_shipping_address_remains_immutable_snapshot_after_later_profile_edits(): void
    {
        [$cart, $user] = $this->cartWithUser();
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB', 'save_default_address' => '1'];
        $this->actingAs($user)->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertRedirect();
        $order = $cart->fresh()->convertedOrder;

        $this->actingAs($user)->patchJson(route('account.details.update'), [
            'address_line_1' => '50 New Road', 'city' => 'Bristol', 'postcode' => 'BS1 1AA',
        ])->assertOk();

        $this->assertSame('1 High Street', $order->fresh()->shipping_address['address_line_1']);
        $this->assertSame('London', $order->fresh()->shipping_address['city']);
    }

    public function test_checkout_page_renders_shared_component_field_markup(): void
    {
        [$cart] = $this->cart();

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.show'))->assertOk()
            ->assertSee('autocomplete="given-name"', false)->assertSee('autocomplete="family-name"', false)
            ->assertSee('autocomplete="shipping address-line1"', false)->assertSee('autocomplete="shipping postal-code"', false);
    }

    public function test_checkout_store_still_requires_full_address_on_submission(): void
    {
        [$cart] = $this->cart();
        $payload = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => fake()->uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'), 'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com', 'address_line_1' => '1 High Street', 'postcode' => 'SW1A1AA', 'country' => 'GB'];

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $payload)->assertSessionHasErrors('city');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array{0: Cart, 1: User}
     */
    private function cartWithUser(string $email = 'mia@example.com'): array
    {
        [$cart] = $this->cart();
        $user = User::factory()->create(['email' => $email, 'is_admin' => false]);

        return [$cart, $user];
    }

    /**
     * @return array{0: Cart}
     */
    private function cart(string $token = 'owner-secret'): array
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

        $this->withCookie('cattie_guest_token', $token)->post(route('artwork.cart', $session->fresh()->public_id));
        $cart = Cart::query()->firstOrFail();

        return [$cart];
    }

    private function profile(User $user, array $attributes, array $address = []): CustomerProfile
    {
        return CustomerProfile::query()->create([
            'user_id' => $user->id,
            ...$attributes,
            'default_shipping_address' => $address === [] ? null : ['country' => 'GB', ...$address],
        ]);
    }
}
