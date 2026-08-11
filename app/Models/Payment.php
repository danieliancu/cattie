<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'provider_metadata' => 'array', 'completed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
