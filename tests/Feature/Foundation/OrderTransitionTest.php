<?php

namespace Tests\Feature\Foundation;

use App\Domain\Orders\Actions\TransitionOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_changes_state_and_writes_audit_record(): void
    {
        $order = $this->order();
        $result = app(TransitionOrder::class)->handle($order, OrderStatus::Personalising, reason: 'Customer began');
        $this->assertSame(OrderStatus::Personalising, $result->status);
        $this->assertDatabaseHas('order_status_transitions', ['order_id' => $order->id, 'from_status' => 'draft', 'to_status' => 'personalising']);
    }

    public function test_invalid_transition_is_rejected_without_mutation(): void
    {
        $order = $this->order();
        try {
            app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
            $this->fail('Expected invalid transition.');
        } catch (ValidationException) {
            $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
            $this->assertDatabaseCount('order_status_transitions', 0);
        }
    }

    private function order(): Order
    {
        return Order::query()->create(['number' => 'CAT-TEST-1', 'email' => 'customer@example.test', 'status' => OrderStatus::Draft, 'currency' => 'GBP', 'subtotal_minor' => 2000, 'shipping_minor' => 0, 'tax_minor' => 0, 'total_minor' => 2000, 'shipping_address' => ['line1' => '1 Test Road', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB']]);
    }
}
