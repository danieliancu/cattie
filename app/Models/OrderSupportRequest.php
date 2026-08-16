<?php

namespace App\Models;

use App\Enums\OrderSupportStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class OrderSupportRequest extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['photo_storage_key'];

    protected function casts(): array
    {
        return ['status' => OrderSupportStatus::class, 'contact_email' => 'encrypted'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
