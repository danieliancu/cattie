<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Contracts\ShippingResolver;
use App\Domain\Payments\Contracts\TaxResolver;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveOrderTotals
{
    public function __construct(private ShippingResolver $shipping, private TaxResolver $tax, private OrderPayability $payability) {}

    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->status === OrderStatus::Paid) {
                return $order;
            }
            if ($order->status !== OrderStatus::AwaitingPayment) {
                throw ValidationException::withMessages(['order' => 'This order is not ready for payment.']);
            }

            $subtotal = $order->items->sum('total_price_minor');
            $shipping = $this->shipping->resolve($order);
            $tax = $this->tax->resolve($order, $shipping);
            $total = $subtotal + $shipping + $tax - $order->discount_minor;
            $order->update(['subtotal_minor' => $subtotal, 'shipping_minor' => $shipping, 'tax_minor' => $tax,
                'total_minor' => $total, 'shipping_status' => 'resolved', 'tax_status' => 'resolved',
                'totals_status' => 'resolved', 'is_payable' => false]);
            $order->refresh();
            $order->update(['is_payable' => $this->payability->check($order)]);

            return $order->refresh();
        });
    }
}
