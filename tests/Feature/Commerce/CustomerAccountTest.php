<?php

namespace Tests\Feature\Commerce;

use App\Domain\Cart\Actions\MergeCustomerCart;
use App\Enums\OrderStatus;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registers_verifies_by_email_code_then_can_login_and_logout(): void
    {
        Notification::fake();
        $response = $this->post(route('register.store'), ['email' => 'CUSTOMER@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $user = User::query()->where('email', 'customer@example.com')->firstOrFail();

        // Registration creates the account but holds it unverified behind the email code.
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->name);
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('password123', $user->password));

        // The account area is gated until the email is confirmed.
        $this->get(route('account.index'))->assertRedirect(route('verification.notice'));

        // The code screen renders with the pending email and a resend option.
        $this->get(route('verification.notice'))->assertOk()->assertSee('customer@example.com')->assertSee('send a new code');

        $code = null;
        Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        // A wrong code is rejected; the correct one verifies and opens the account.
        $this->post(route('register.verify.store'), ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->post(route('register.verify.store'), ['code' => $code])->assertRedirect(route('account.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->get('/admin')->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password123', 'remember' => '1'])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_duplicate_email_and_admin_cannot_use_customer_login(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'is_admin' => false]);
        $this->post(route('register.store'), ['email' => 'taken@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('email');
        $admin = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password123', 'is_admin' => true]);
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_routes_and_header_follow_authentication_state(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->get(route('home'))->assertOk()->assertSee(route('login'))->assertSee('Create an account');
        $user = User::factory()->create(['name' => null, 'is_admin' => false]);
        $this->actingAs($user)->get(route('account.index'))->assertOk()->assertSee('Your name')->assertSee('Change password')->assertSee('Delete account')
            ->assertSee('action="/logout"', false)->assertSee('name="_token"', false);
        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee(route('account.index'))->assertSee('My Orders')->assertSee('Sign out');
    }

    public function test_order_history_is_user_scoped_sorted_and_human_readable(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['email' => $owner->email.'x', 'is_admin' => false]);
        $older = $this->order($owner, 'CAT-OLDER', OrderStatus::AwaitingPayment, now()->subDay());
        $newer = $this->order($owner, 'CAT-NEWER', OrderStatus::SubmittedToFulfilment, now());
        $foreign = $this->order($other, 'CAT-FOREIGN', OrderStatus::Paid, now()->addMinute(), $owner->email);

        $page = $this->actingAs($owner)->get(route('account.orders.index'))->assertOk()
            ->assertSee('CAT-NEWER')->assertSee('CAT-OLDER')->assertDontSee('CAT-FOREIGN')
            ->assertSee('Sent to production')->assertSee('Awaiting payment');
        $this->assertTrue(strpos($page->getContent(), 'CAT-NEWER') < strpos($page->getContent(), 'CAT-OLDER'));
        $this->actingAs($owner)->get(route('account.orders.show', $newer->number))->assertOk()->assertSee('Order summary')->assertSee('Delivery address');
        $this->actingAs($owner)->get(route('account.orders.show', $foreign->number))->assertNotFound();
        $this->actingAs($owner)->get(route('account.orders.show', 'MISSING'))->assertNotFound();
    }

    public function test_verified_guest_order_is_claimed_individually_and_idempotently(): void
    {
        Notification::fake();
        $claimable = $this->order(null, 'CAT-CLAIM', OrderStatus::Paid, now(), 'mia@example.com', 'owner-token');
        $sameEmail = $this->order(null, 'CAT-NOT-CLAIMED', OrderStatus::Paid, now(), 'mia@example.com', 'different-token');
        $this->withCookie('cattie_guest_token', 'owner-token')->post(route('register.store'), [
            'email' => 'mia@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'claim_order' => $claimable->number,
        ])->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'mia@example.com')->firstOrFail();

        // The claim is deferred until the email code is confirmed.
        $this->assertNull($claimable->fresh()->user_id);

        $code = null;
        Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->withCookie('cattie_guest_token', 'owner-token')->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('account.orders.show', $claimable->number));
        $this->assertSame($user->id, $claimable->fresh()->user_id);
        $this->assertNull($sameEmail->fresh()->user_id);
        $this->withCookie('cattie_guest_token', 'owner-token')->actingAs($user->fresh())->get(route('account.orders.show', $claimable->number))->assertOk();
    }

    public function test_login_preserves_browser_cart_and_merges_existing_user_cart(): void
    {
        $user = User::factory()->create(['password' => 'password123', 'is_admin' => false]);
        $browser = Cart::query()->create(['access_token_hash' => hash('sha256', 'browser-token'), 'status' => 'active', 'currency' => 'GBP', 'expires_at' => now()->addDay()]);
        $existing = Cart::query()->create(['user_id' => $user->id, 'access_token_hash' => hash('sha256', 'old-token'), 'status' => 'active', 'currency' => 'GBP', 'expires_at' => now()->addDay()]);
        foreach ([[$browser, 'Browser gift'], [$existing, 'Saved gift']] as [$cart, $name]) {
            $product = Product::factory()->create(['name' => $name]);
            $cart->items()->create(['product_id' => $product->id, 'product_name' => $name, 'personalisation' => [], 'quantity' => 1, 'unit_price_minor' => 1000, 'currency' => 'GBP']);
        }

        $this->withCookie('cattie_guest_token', 'browser-token')->post(route('login.store'), ['email' => $user->email, 'password' => 'password123'])->assertRedirect(route('account.index'));
        $this->assertSame($user->id, $browser->fresh()->user_id);
        $this->assertSame('merged', $existing->fresh()->status);
        $this->assertCount(2, $browser->fresh('items')->items);
    }

    public function test_private_order_artwork_is_available_only_to_order_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        $order = $this->order($owner, 'CAT-ARTWORK', OrderStatus::Paid, now());
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $style = ArtworkStyle::query()->create(['name' => 'Storybook Cartoon', 'slug' => 'private-style', 'prompt_key' => 'storybook', 'is_active' => true]);
        $session = ArtworkSession::query()->create(['public_id' => (string) Str::ulid(), 'access_token_hash' => hash('sha256', 'asset-owner'), 'user_id' => $owner->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation_snapshot' => [], 'status' => ArtworkSessionStatus::Approved, 'expires_at' => now()->addDay()]);
        Storage::disk('local')->put('account-preview.webp', 'preview');
        $upload = $session->uploads()->create(['user_id' => $owner->id, 'disk' => 'local', 'storage_key' => 'input.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'account-preview.webp', 'mime_type' => 'image/webp']);
        $item = $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_session_id' => $session->id, 'generation_id' => $generation->id, 'generation_asset_id' => $asset->id, 'product_name' => 'Private Gift', 'variant_name' => 'Silver', 'artwork_style_name' => 'Storybook Cartoon', 'sku' => $variant->sku, 'personalisation' => [], 'quantity' => 1, 'unit_price_minor' => 1950, 'total_price_minor' => 1950, 'currency' => 'GBP']);

        $this->actingAs($owner)->get(route('account.orders.artwork', [$order->number, $item]))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($owner)->get(route('account.orders.show', $order->number))->assertOk()->assertSee('Private Gift')->assertSee('Cartoon')->assertDontSee('Storybook Cartoon');
        $this->actingAs($other)->get(route('account.orders.artwork', [$order->number, $item]))->assertNotFound();
        $this->post(route('logout'));
        $this->get(route('account.orders.artwork', [$order->number, $item]))->assertRedirect(route('login'));
    }

    private function order(?User $user, string $number, OrderStatus $status, $placedAt, ?string $email = null, string $token = 'token'): Order
    {
        return Order::query()->create(['number' => $number, 'user_id' => $user?->id, 'access_token_hash' => hash('sha256', $token), 'email' => $email ?? $user?->email ?? 'guest@example.com', 'status' => $status, 'currency' => 'GBP', 'subtotal_minor' => 1950, 'discount_minor' => 0, 'shipping_minor' => 350, 'tax_minor' => 0, 'total_minor' => 2300, 'shipping_status' => 'resolved', 'tax_status' => 'resolved', 'totals_status' => 'resolved', 'is_payable' => false, 'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'], 'placed_at' => $placedAt]);
    }
}
