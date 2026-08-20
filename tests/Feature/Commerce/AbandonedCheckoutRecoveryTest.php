<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Mail\AbandonedCheckoutMail;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AbandonedCheckoutRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = [], ?string $createdAgoHours = null, bool $withItem = false): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => 'CAT-'.substr(md5((string) mt_rand()), 0, 6),
            'access_token_hash' => hash('sha256', 'token-'.mt_rand()),
            'email' => 'lost@example.com',
            'status' => OrderStatus::AwaitingPayment,
            'currency' => 'GBP',
            'subtotal_minor' => 2499, 'discount_minor' => 0, 'shipping_minor' => 350, 'tax_minor' => 0, 'total_minor' => 2849,
            'shipping_status' => 'resolved', 'tax_status' => 'resolved', 'totals_status' => 'resolved', 'is_payable' => true,
            'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'],
            'placed_at' => now(),
        ], $overrides));

        if ($createdAgoHours !== null) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subHours((int) $createdAgoHours)]);
        }
        if ($withItem) {
            $order->items()->create(['product_name' => 'Water Bottle with Red Flip Lid', 'variant_name' => 'White', 'sku' => 'WB-1', 'personalisation' => [], 'artwork_style_name' => 'Storybook Cartoon', 'quantity' => 1, 'unit_price_minor' => 2499, 'total_price_minor' => 2499, 'currency' => 'GBP']);
        }

        return $order->fresh();
    }

    public function test_first_reminder_is_sent_after_an_hour_and_never_repeated(): void
    {
        Mail::fake();
        $order = $this->order([], createdAgoHours: 2);

        $this->artisan('orders:send-abandoned-recovery')->assertSuccessful();
        Mail::assertQueued(AbandonedCheckoutMail::class, fn ($m) => $m->stage === 1 && $m->hasTo('lost@example.com'));
        $this->assertNotNull($order->fresh()->recovery_first_sent_at);

        $this->artisan('orders:send-abandoned-recovery')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }

    public function test_second_reminder_is_sent_after_a_day(): void
    {
        Mail::fake();
        $order = $this->order(['recovery_first_sent_at' => now()->subDay()], createdAgoHours: 25);

        $this->artisan('orders:send-abandoned-recovery')->assertSuccessful();
        Mail::assertQueued(AbandonedCheckoutMail::class, fn ($m) => $m->stage === 2 && $m->hasTo('lost@example.com'));
        $this->assertNotNull($order->fresh()->recovery_second_sent_at);

        $this->artisan('orders:send-abandoned-recovery')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }

    public function test_recovery_skips_paid_unsubscribed_too_new_and_too_old_orders(): void
    {
        Mail::fake();
        $this->order(['status' => OrderStatus::Paid], createdAgoHours: 2);
        $this->order(['status' => OrderStatus::Cancelled], createdAgoHours: 2);
        $this->order(['recovery_unsubscribed_at' => now()], createdAgoHours: 2);
        $this->order([], createdAgoHours: 0); // too new (created just now)
        $this->order([], createdAgoHours: 24 * 4); // older than the 3-day cutoff

        $this->artisan('orders:send-abandoned-recovery')->assertSuccessful();
        Mail::assertNothingQueued();
    }

    public function test_resume_link_restores_access_and_redirects_to_payment(): void
    {
        $order = $this->order([], createdAgoHours: 2);
        $originalHash = $order->access_token_hash;

        $url = URL::signedRoute('checkout.resume', ['order' => $order->id]);
        $this->get($url)->assertRedirect(route('checkout.payment', $order->number));
        $this->assertNotSame($originalHash, $order->fresh()->access_token_hash);

        // An unsigned request is rejected.
        $this->get(route('checkout.resume', ['order' => $order->id]))->assertForbidden();
    }

    public function test_stop_reminders_link_unsubscribes_the_order(): void
    {
        $order = $this->order([], createdAgoHours: 2);

        $url = URL::signedRoute('orders.stop-reminders', ['order' => $order->id]);
        $this->get($url)->assertOk()->assertSee('Reminders stopped');
        $this->assertNotNull($order->fresh()->recovery_unsubscribed_at);
    }

    public function test_recovery_email_renders_with_cta_and_unsubscribe(): void
    {
        $order = $this->order([], createdAgoHours: 2, withItem: true);
        $html = (new AbandonedCheckoutMail($order, 1))->render();

        $this->assertStringContainsString('Complete your order', $html);
        $this->assertStringContainsString('Water Bottle with Red Flip Lid', $html);
        $this->assertStringContainsString('Stop reminders about this order', $html);
    }
}
