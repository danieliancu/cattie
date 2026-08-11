<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class PrintAsset extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['status' => ProcessingStatus::class, 'specification' => 'array'];
    }
}
