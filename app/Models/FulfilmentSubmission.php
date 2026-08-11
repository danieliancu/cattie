<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class FulfilmentSubmission extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['request_payload', 'response_payload'];

    protected function casts(): array
    {
        return ['status' => ProcessingStatus::class, 'request_payload' => 'array', 'response_payload' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
