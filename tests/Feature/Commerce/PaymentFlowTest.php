<?php

namespace Tests\Feature\Commerce;

use App\Contracts\PaymentProvider;
use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Domain\Payments\Actions\OrderPayability;
use App\Domain\Payments\Actions\ResolveOrderTotals;
use App\Domain\Payments\Actions\StartPayment;
use App\Enums\GenerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ArtworkStyle;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Providers\Payments\FakePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.provider' => 'fake', 'payments.fake.enabled' => true]);
    }

    private function awaitingOrder(string $token = 'owner-secret'): Order
    {
        Storage::fake('local');
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price_minor' => 2499]);
        $variant->fulfilmentMappings()->create(['provider' => 'treatpod', 'provider_sku' => 'TEST-SKU', 'configuration' => [], 'is_active' => true]);
        $shipping = ShippingMethod::query()->firstOrCreate(['provider' => 'treatpod', 'code' => 'test-standard'], ['name' => 'Royal Mail 48 Tracked', 'provider_service_code' => 'RM48', 'price_minor' => 350, 'currency' => 'GBP', 'country' => 'GB', 'estimated_business_days_min' => 5, 'estimated_business_days_max' => 8, 'is_active' => true]);
        $style = ArtworkStyle::query()->create(['name' => 'Storybook Cartoon', 'slug' => 'storybook-cartoon', 'prompt_key' => 'storybook', 'is_active' => true]);
        $product->artworkStyles()->attach($style);
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => []], $token);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'original', 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'storybook-v1', 'prompt_version' => 1, 'resolved_prompt' => 'safe', 'provider' => 'fake', 'model' => 'gpt-image-2', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'USD']);
        Storage::disk('local')->put('preview.webp', 'preview');
        $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'preview.webp', 'mime_type' => 'image/webp']);
        app(ApproveArtwork::class)->handle($session, $asset);

        $order = Order::query()->create(['number' => 'CAT-2608-ABC123', 'access_token_hash' => hash('sha256', $token), 'checkout_idempotency_key' => (string) Str::uuid(), 'email' => 'mia@example.com', 'status' => OrderStatus::AwaitingPayment, 'currency' => 'GBP', 'subtotal_minor' => 2499, 'discount_minor' => 0, 'shipping_minor' => null, 'tax_minor' => null, 'total_minor' => null, 'shipping_status' => 'unresolved', 'tax_status' => 'unresolved', 'totals_status' => 'unresolved', 'is_payable' => false, 'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'], 'shipping_method_id' => $shipping->id, 'shipping_method_snapshot' => $shipping->snapshot(), 'placed_at' => now()]);
        $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_session_id' => $session->id, 'generation_id' => $generation->id, 'generation_asset_id' => $asset->id, 'product_name' => $product->name, 'variant_name' => $variant->name, 'artwork_style_name' => $style->name, 'sku' => $variant->sku, 'personalisation' => [], 'artwork_snapshot' => ['asset_id' => $asset->id], 'quantity' => 1, 'unit_price_minor' => 2499, 'total_price_minor' => 2499, 'currency' => 'GBP']);

        return $order;
    }

    public function test_totals_resolve_in_minor_units_and_make_valid_order_payable(): void
    {
        $order = $this->awaitingOrder();
        $this->assertFalse(app(OrderPayability::class)->check($order));
        $order = app(ResolveOrderTotals::class)->handle($order);
        $this->assertSame(350, $order->shipping_minor);
        $this->assertSame(0, $order->tax_minor);
        $this->assertSame(2849, $order->total_minor);
        $this->assertTrue($order->is_payable);
        $this->assertTrue(app(OrderPayability::class)->check($order));
    }

    public function test_fake_provider_is_bound_and_disabled_mode_fails_safely(): void
    {
        $this->assertInstanceOf(FakePaymentProvider::class, app(PaymentProvider::class));
        $order = app(ResolveOrderTotals::class)->handle($this->awaitingOrder());
        config(['payments.fake.enabled' => false]);
        try {
            app(StartPayment::class)->handle($order, (string) Str::uuid(), 'success');
            $this->fail('Disabled fake payment should not start.');
        } catch (ValidationException $exception) {
            $this->assertSame("We couldn't start your payment. Please try again.", $exception->errors()['payment'][0]);
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_failure_and_cancellation_are_preserved_then_retry_can_succeed(): void
    {
        $order = app(ResolveOrderTotals::class)->handle($this->awaitingOrder());
        $failed = app(StartPayment::class)->handle($order, (string) Str::uuid(), 'failure');
        $cancelled = app(StartPayment::class)->handle($order->fresh(), (string) Str::uuid(), 'cancelled');
        $succeeded = app(StartPayment::class)->handle($order->fresh(), (string) Str::uuid(), 'success');
        $this->assertSame(PaymentStatus::Failed, $failed->status);
        $this->assertSame(PaymentStatus::Cancelled, $cancelled->status);
        $this->assertSame(PaymentStatus::Succeeded, $succeeded->status);
        $this->assertDatabaseCount('payments', 3);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('order_status_transitions', 1);
    }

    public function test_duplicate_payment_submission_is_idempotent_and_amount_is_server_derived(): void
    {
        $order = app(ResolveOrderTotals::class)->handle($this->awaitingOrder());
        $key = (string) Str::uuid();
        $first = app(StartPayment::class)->handle($order, $key, 'success');
        $second = app(StartPayment::class)->handle($order->fresh(), $key, 'success');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(2849, $first->amount_minor);
        $this->assertSame('GBP', $first->currency);
        $this->assertStringStartsWith('fake_pay_', $first->external_id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_status_transitions', 1);
    }

    public function test_guest_payment_route_completes_lifecycle_and_confirmation_is_private(): void
    {
        $order = $this->awaitingOrder();
        $this->withCookie('cattie_guest_token', 'other')->get(route('checkout.payment', $order->number))->assertNotFound();
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.payment', $order->number))->assertOk()->assertSee('£24.99');
        $key = (string) Str::uuid();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.pay', $order->number), ['idempotency_key' => $key, 'scenario' => 'success', 'amount_minor' => 1])->assertRedirect(route('orders.confirmation', $order->number));
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('orders.confirmation', $order->number))->assertOk()->assertSee('Thank you')->assertSee($order->number);
        $this->withCookie('cattie_guest_token', 'other')->get(route('orders.confirmation', $order->number))->assertNotFound();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('analytics_events', ['name' => 'payment_started']);
        $this->assertDatabaseHas('analytics_events', ['name' => 'payment_succeeded']);
        $this->assertDatabaseHas('analytics_events', ['name' => 'order_paid']);
    }

    public function test_complete_commercial_lifecycle_reaches_paid(): void
    {
        $fixture = $this->awaitingOrder();
        $session = $fixture->items()->first()->artworkSession;
        $fixture->delete();

        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('artwork.cart', $session->public_id))->assertRedirect(route('cart.index'));
        $cart = Cart::query()->firstOrFail();
        $checkout = ['pricing_hash' => $cart->pricing_hash, 'checkout_idempotency_key' => (string) Str::uuid(), 'shipping_method_id' => ShippingMethod::query()->value('id'),
            'first_name' => 'Mia', 'last_name' => 'Smith', 'email' => 'mia@example.com',
            'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'];
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.store'), $checkout)->assertRedirect();
        $order = $cart->fresh()->convertedOrder;
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);

        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('checkout.payment', $order->number))->assertOk();
        $this->withCookie('cattie_guest_token', 'owner-secret')->post(route('checkout.pay', $order->number), [
            'idempotency_key' => (string) Str::uuid(), 'scenario' => 'success',
        ])->assertRedirect(route('orders.confirmation', $order->number));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('converted', $cart->fresh()->status);
        $this->withCookie('cattie_guest_token', 'owner-secret')->get(route('cart.index'))->assertOk()->assertSee('Your basket is empty.');
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'artwork_session_id' => $session->id]);
    }
}
