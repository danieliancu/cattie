<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['access_token_hash', 'checkout_idempotency_key', 'shipping_address'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'shipping_address' => 'encrypted:array', 'shipping_method_snapshot' => 'array', 'is_payable' => 'boolean', 'placed_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class)->withTrashed();
    }

    public function transitions()
    {
        return $this->hasMany(OrderStatusTransition::class);
    }
}
