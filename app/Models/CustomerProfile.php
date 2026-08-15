<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['default_shipping_address'];

    protected function casts(): array
    {
        return ['default_shipping_address' => 'encrypted:array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
