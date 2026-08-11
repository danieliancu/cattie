<?php

namespace App\Models;

use App\Enums\WebhookStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['status' => WebhookStatus::class, 'payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
