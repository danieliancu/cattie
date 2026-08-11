<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['properties' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
