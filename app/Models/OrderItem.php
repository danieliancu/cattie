<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['personalisation' => 'array', 'artwork_snapshot' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function artworkSession()
    {
        return $this->belongsTo(ArtworkSession::class);
    }

    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }

    public function generationAsset()
    {
        return $this->belongsTo(GenerationAsset::class);
    }
}
