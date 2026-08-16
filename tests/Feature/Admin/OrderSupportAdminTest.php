<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderSupportStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderSupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderSupportAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_non_admin_cannot_access_order_support_admin_resource(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-1']);
        $request = $this->supportRequest($order);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/order-support-requests')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get("/admin/order-support-requests/{$request->id}/edit")->assertForbidden();
    }

    public function test_admin_can_list_order_support_requests(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-2']);
        $this->supportRequest($order);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/order-support-requests')->assertOk()->assertSee('CAT-ADMIN-2');
    }

    public function test_admin_can_inspect_a_support_request(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-3']);
        $request = $this->supportRequest($order, 'The gift arrived with the wrong name on it.');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/admin/order-support-requests/{$request->id}/edit")
            ->assertOk()->assertSee($request->reference)->assertSee('The gift arrived with the wrong name on it.');
    }

    public function test_admin_can_change_status_open_to_reviewing(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-4']);
        $request = $this->supportRequest($order);
        $admin = User::factory()->create(['is_admin' => true]);

        $request->update(['status' => OrderSupportStatus::Reviewing]);

        $this->assertSame(OrderSupportStatus::Reviewing, $request->fresh()->status);
    }

    public function test_admin_can_change_status_to_resolved_or_closed(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-5']);
        $request = $this->supportRequest($order);

        $request->update(['status' => OrderSupportStatus::Resolved]);
        $this->assertSame(OrderSupportStatus::Resolved, $request->fresh()->status);

        $request->update(['status' => OrderSupportStatus::Closed]);
        $this->assertSame(OrderSupportStatus::Closed, $request->fresh()->status);
    }

    public function test_support_status_change_does_not_modify_order_status(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-6', 'status' => OrderStatus::Paid]);
        $request = $this->supportRequest($order);

        $request->update(['status' => OrderSupportStatus::Closed]);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_admin_can_securely_view_and_download_support_photo(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-ADMIN-7']);
        Storage::disk('local')->put('order-support/test/photo.jpg', 'fake-bytes');
        $request = OrderSupportRequest::query()->create([
            'reference' => 'SUP-ADMIN1', 'order_id' => $order->id, 'contact_email' => 'guest@example.com',
            'message' => 'Photo evidence attached.', 'status' => OrderSupportStatus::Open,
            'photo_disk' => 'local', 'photo_storage_key' => 'order-support/test/photo.jpg', 'photo_mime_type' => 'image/jpeg', 'photo_size_bytes' => 10,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.order-support.photo', $request))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    private function supportRequest(Order $order, string $message = 'Something was wrong with my order.'): OrderSupportRequest
    {
        return OrderSupportRequest::query()->create([
            'reference' => 'SUP-'.strtoupper(str()->random(6)),
            'order_id' => $order->id,
            'contact_email' => 'guest@example.com',
            'message' => $message,
            'status' => OrderSupportStatus::Open,
        ]);
    }
}
