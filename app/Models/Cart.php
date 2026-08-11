<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['guest_token', 'access_token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function convertedOrder()
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function subtotalMinor(): int
    {
        return $this->items->sum(fn (CartItem $item) => $item->unit_price_minor * $item->quantity);
    }
}
