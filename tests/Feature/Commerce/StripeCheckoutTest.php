<?php

namespace Tests\Feature\Commerce;

use App\Contracts\PaymentProvider;
use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Domain\Payments\Actions\StartPayment;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Integrations\Stripe\StripeGateway;
use App\Models\ArtworkStyle;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Providers\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeGateway $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'payments.provider' => 'stripe',
            'payments.stripe.secret_key' => 'sk_test_example',
            'payments.stripe.publishable_key' => 'pk_test_example',
            'payments.stripe.webhook_secret' => 'whsec_example',
        ]);
        $this->stripe = new FakeStripeGateway;
        $this->app->instance(StripeGateway::class, $this->stripe);
    }

    public function test_stripe_provider_is_resolved_and_checkout_uses_dynamic_order_snapshots(): void
    {
        $this->assertInstanceOf(StripePaymentProvider::class, app(PaymentProvider::class));
        $order = $this->awaitingOrder([
            ['name' => 'Water Bottle with Red Flip Lid', 'variant' => 'White · 750 ml', 'price' => 1650, 'quantity' => 2, 'personalisation' => [['label' => 'Name', 'value' => 'Anna']]],
            ['name' => 'Pencil Tin', 'variant' => 'Blue', 'price' => 1795, 'quantity' => 1, 'personalisation' => [['label' => 'Name', 'value' => 'Mia']]],
        ]);

        $payment = app(StartPayment::class)->handle($order, $key = (string) Str::uuid());

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $payload = $this->stripe->created[0]['parameters'];
        $this->assertCount(2, $payload['line_items']);
        $this->assertSame('Water Bottle with Red Flip Lid', $payload['line_items'][0]['price_data']['product_data']['name']);
        $this->assertStringContainsString('White · 750 ml', $payload['line_items'][0]['price_data']['product_data']['description']);
        $this->assertStringContainsString('Name: Anna', $payload['line_items'][0]['price_data']['product_data']['description']);
        $this->assertSame(1650, $payload['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(2, $payload['line_items'][0]['quantity']);
        $this->assertSame('gbp', $payload['line_items'][0]['price_data']['currency']);
        $this->assertSame($key, $this->stripe->created[0]['idempotency_key']);
        $this->assertSame($order->number, $payload['client_reference_id']);
        $this->assertSame($payment->id, $payload['metadata']['cattie_payment_id']);
        $this->assertSame('embedded', $payload['ui_mode']);
        $this->assertSame('if_required', $payload['redirect_on_completion']);
        $this->assertStringEndsWith('?session_id={CHECKOUT_SESSION_ID}', $payload['return_url']);
        $this->assertArrayNotHasKey('success_url', $payload);
        $this->assertArrayNotHasKey('cancel_url', $payload);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('storage', $encoded);
        $this->assertStringNotContainsString('generation', $encoded);
        $this->assertArrayNotHasKey('price', $payload['line_items'][0]);
        $this->assertArrayNotHasKey('id', $payload['line_items'][0]['price_data']['product_data']);
    }

    public function test_shipping_tax_and_total_are_authoritative_and_repeat_start_is_idempotent(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'Blue', 'price' => 1000, 'quantity' => 1]], shipping: 250, tax: 200);
        $key = (string) Str::uuid();
        $first = app(StartPayment::class)->handle($order, $key);
        $second = app(StartPayment::class)->handle($order->fresh(), $key);

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $this->stripe->created);
        $lineItems = $this->stripe->created[0]['parameters']['line_items'];
        $this->assertSame(['Gift', 'Royal Mail 48 Tracked', 'Tax'], array_column(array_column(array_column($lineItems, 'price_data'), 'product_data'), 'name'));
        $this->assertSame(1450, collect($lineItems)->sum(fn ($item) => $item['price_data']['unit_amount'] * $item['quantity']));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_page_mounts_embedded_checkout_and_session_endpoint_returns_only_transient_secret(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'Pink', 'price' => 1200, 'quantity' => 1]]);
        $client = $this->withCookie('cattie_guest_token', 'stripe-owner');
        $client->get(route('checkout.payment', $order->number))
            ->assertOk()->assertSee('stripe-embedded-checkout')->assertSee('pk_test_example')
            ->assertSee('lg:flex-row lg:items-start', false)
            ->assertDontSee('Your card details never pass through Cattie.');
        $response = $client->post(route('checkout.stripe-session', $order->number), ['idempotency_key' => (string) Str::uuid()], ['Accept' => 'application/json']);
        $response->assertOk()->assertJson(['status' => 'pending', 'client_secret' => 'cs_test_1_secret_test']);
        $payment = $order->payments()->firstOrFail();
        $this->assertArrayNotHasKey('client_secret', $payment->provider_metadata);
        $this->assertArrayNotHasKey('checkout_url', $payment->provider_metadata);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_refresh_reuses_open_embedded_session_and_status_reconciles_payment(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'Pink', 'price' => 1200, 'quantity' => 1]]);
        $cookie = $this->withCookie('cattie_guest_token', 'stripe-owner');
        $cookie->post(route('checkout.stripe-session', $order->number), ['idempotency_key' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertOk();
        $cookie->post(route('checkout.stripe-session', $order->number), ['idempotency_key' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertOk()
            ->assertJson(['client_secret' => 'cs_test_1_secret_test']);
        $this->assertCount(1, $this->stripe->created);
        $this->assertDatabaseCount('payments', 1);

        $payment = $order->payments()->firstOrFail();
        $this->stripe->sessions[$payment->external_id] = $this->stripe->paidSession($payment->external_id);
        $cookie->post(route('checkout.stripe-status', $order->number), [], ['Accept' => 'application/json'])->assertOk()
            ->assertJson(['status' => 'paid', 'confirmation_url' => route('orders.confirmation', $order->number)]);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_signed_paid_webhook_completes_once_and_rejects_mismatches_or_bad_signature(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'Silver', 'price' => 1500, 'quantity' => 1]]);
        $payment = app(StartPayment::class)->handle($order, (string) Str::uuid());
        $this->stripe->event('evt_paid', 'checkout.session.completed', $this->stripe->paidSession($payment->external_id));

        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('order_status_transitions', 1);
        $this->assertDatabaseCount('webhook_events', 1);

        $other = $this->awaitingOrder([['name' => 'Other', 'variant' => 'Blue', 'price' => 900, 'quantity' => 1]], token: 'other-owner');
        $otherPayment = app(StartPayment::class)->handle($other, (string) Str::uuid());
        $bad = $this->stripe->paidSession($otherPayment->external_id);
        $bad['amount_total']++;
        $this->stripe->event('evt_mismatch', 'checkout.session.completed', $bad);
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertStatus(422);
        $this->assertSame(OrderStatus::AwaitingPayment, $other->fresh()->status);

        $this->stripe->event('evt_bad_signature', 'checkout.session.completed', $this->stripe->paidSession($otherPayment->external_id));
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'invalid'])->assertStatus(400);
    }

    public function test_async_failure_and_expiry_leave_order_retryable(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'Pink', 'price' => 1300, 'quantity' => 1]]);
        $failed = app(StartPayment::class)->handle($order, (string) Str::uuid());
        $this->stripe->event('evt_failed', 'checkout.session.async_payment_failed', $this->stripe->session($failed->external_id));
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();
        $this->assertSame(PaymentStatus::Failed, $failed->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);

        $expired = app(StartPayment::class)->handle($order->fresh(), (string) Str::uuid());
        $session = $this->stripe->session($expired->external_id);
        $session['status'] = 'expired';
        $this->stripe->event('evt_expired', 'checkout.session.expired', $session);
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();
        $this->assertSame(PaymentStatus::Cancelled, $expired->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_success_return_and_webhook_race_complete_once_and_refund_uses_payment_intent(): void
    {
        $order = $this->awaitingOrder([['name' => 'Gift', 'variant' => 'White', 'price' => 1800, 'quantity' => 1]]);
        $payment = app(StartPayment::class)->handle($order, (string) Str::uuid());
        $this->stripe->sessions[$payment->external_id] = $this->stripe->paidSession($payment->external_id);

        $this->withCookie('cattie_guest_token', 'stripe-owner')->get(route('checkout.stripe-return', [$order->number, 'session_id' => $payment->external_id]))
            ->assertRedirect(route('orders.confirmation', $order->number));
        $this->stripe->event('evt_race', 'checkout.session.completed', $this->stripe->paidSession($payment->external_id));
        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();
        $this->assertDatabaseCount('order_status_transitions', 1);
        $this->assertDatabaseHas('analytics_events', ['name' => 'payment_succeeded']);

        app(StripePaymentProvider::class)->refund($payment->external_id, 500, 'refund-key');
        $this->assertSame(['payment_intent' => 'pi_'.$payment->external_id, 'amount' => 500], $this->stripe->refunds[0]['parameters']);
    }

    private function awaitingOrder(array $items, int $shipping = 0, int $tax = 0, string $token = 'stripe-owner'): Order
    {
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $shippingMethod = ShippingMethod::query()->create([
            'provider' => 'treatpod', 'code' => 'stripe-'.Str::lower(Str::random(8)), 'name' => 'Royal Mail 48 Tracked',
            'provider_service_code' => 'RM48', 'price_minor' => $shipping, 'currency' => 'GBP', 'country' => 'GB',
            'estimated_business_days_min' => 5, 'estimated_business_days_max' => 8, 'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'CAT-'.strtoupper(Str::random(10)), 'access_token_hash' => hash('sha256', $token),
            'checkout_idempotency_key' => (string) Str::uuid(), 'email' => 'customer@example.com',
            'status' => OrderStatus::AwaitingPayment, 'currency' => 'GBP', 'subtotal_minor' => $subtotal,
            'discount_minor' => 0, 'shipping_minor' => $shipping, 'tax_minor' => $tax,
            'total_minor' => $subtotal + $shipping + $tax, 'shipping_status' => 'resolved', 'tax_status' => 'resolved',
            'totals_status' => 'resolved', 'is_payable' => true,
            'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'],
            'shipping_method_id' => $shippingMethod->id, 'shipping_method_snapshot' => $shippingMethod->snapshot(),
            'placed_at' => now(),
        ]);
        foreach ($items as $index => $item) {
            $product = Product::factory()->create(['name' => $item['name']]);
            $variant = ProductVariant::factory()->for($product)->create(['name' => $item['variant'], 'price_minor' => $item['price']]);
            $style = ArtworkStyle::query()->firstOrCreate(['slug' => 'stripe-style-'.$index.'-'.Str::random(4)], ['name' => 'Cartoon', 'prompt_key' => 'cartoon', 'is_active' => true]);
            $product->artworkStyles()->attach($style);
            [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => []], $token);
            $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'input-'.$session->id, 'mime_type' => 'image/jpeg', 'size_bytes' => 1, 'sha256' => hash('sha256', $session->id)]);
            $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'USD']);
            Storage::disk('local')->put('asset-'.$session->id.'.webp', 'asset');
            $asset = $generation->assets()->create(['kind' => 'web_preview', 'disk' => 'local', 'storage_key' => 'asset-'.$session->id.'.webp', 'mime_type' => 'image/webp']);
            app(ApproveArtwork::class)->handle($session, $asset);
            $order->items()->create([
                'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_session_id' => $session->id,
                'generation_id' => $generation->id, 'generation_asset_id' => $asset->id,
                'product_name' => $item['name'], 'variant_name' => $item['variant'], 'artwork_style_name' => 'Cartoon',
                'sku' => $variant->sku, 'personalisation' => $item['personalisation'] ?? [], 'artwork_snapshot' => [],
                'quantity' => $item['quantity'], 'unit_price_minor' => $item['price'],
                'total_price_minor' => $item['price'] * $item['quantity'], 'currency' => 'GBP',
            ]);
        }

        return $order->fresh('items');
    }
}

final class FakeStripeGateway implements StripeGateway
{
    public array $created = [];
    public array $sessions = [];
    public array $refunds = [];
    private ?array $nextEvent = null;

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        $number = count($this->created) + 1;
        $id = 'cs_test_'.$number;
        $amount = collect($parameters['line_items'])->sum(fn ($item) => $item['price_data']['unit_amount'] * $item['quantity']);
        $session = [
            'id' => $id, 'client_secret' => $id.'_secret_test', 'amount_total' => $amount,
            'currency' => $parameters['line_items'][0]['price_data']['currency'], 'payment_status' => 'unpaid', 'status' => 'open',
            'client_reference_id' => $parameters['client_reference_id'], 'metadata' => $parameters['metadata'], 'payment_intent' => 'pi_'.$id,
        ];
        $this->created[] = compact('parameters', 'idempotencyKey') + ['idempotency_key' => $idempotencyKey];
        $this->sessions[$id] = $session;

        return $session;
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? throw new RuntimeException('Unknown Stripe session.');
    }

    public function expireCheckoutSession(string $sessionId): array
    {
        $this->sessions[$sessionId]['status'] = 'expired';

        return $this->sessions[$sessionId];
    }

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array
    {
        if ($signature !== 'valid' || $secret !== 'whsec_example') throw new RuntimeException('Invalid signature.');

        return $this->nextEvent ?? throw new RuntimeException('No event configured.');
    }

    public function createRefund(array $parameters, string $idempotencyKey): array
    {
        $this->refunds[] = compact('parameters', 'idempotencyKey');

        return ['id' => 're_test', 'status' => 'succeeded'];
    }

    public function event(string $id, string $type, array $session): void
    {
        $this->nextEvent = ['id' => $id, 'type' => $type, 'data' => ['object' => $session]];
    }

    public function session(string $id): array
    {
        return $this->sessions[$id];
    }

    public function paidSession(string $id): array
    {
        return [...$this->sessions[$id], 'payment_status' => 'paid', 'status' => 'complete'];
    }
}
