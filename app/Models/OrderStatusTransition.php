<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class OrderStatusTransition extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'metadata' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
