<?php

namespace App\Models;

use App\Enums\ArtworkSessionStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class ArtworkSession extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['access_token_hash', 'personalisation_snapshot'];

    protected function casts(): array
    {
        return ['status' => ArtworkSessionStatus::class, 'personalisation_snapshot' => 'array', 'approved_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function artworkStyle()
    {
        return $this->belongsTo(ArtworkStyle::class);
    }

    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }

    public function currentUpload()
    {
        return $this->belongsTo(Upload::class, 'current_upload_id');
    }

    public function currentGeneration()
    {
        return $this->belongsTo(Generation::class, 'current_generation_id');
    }

    public function approvedAsset()
    {
        return $this->belongsTo(GenerationAsset::class, 'approved_generation_asset_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
