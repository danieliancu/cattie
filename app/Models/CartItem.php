<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['personalisation' => 'array'];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function generationAsset()
    {
        return $this->belongsTo(GenerationAsset::class);
    }

    public function artworkSession()
    {
        return $this->belongsTo(ArtworkSession::class);
    }

    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }

    public function composedDesign()
    {
        return $this->belongsTo(ComposedDesign::class);
    }

    public function lineTotalMinor(): int
    {
        return $this->unit_price_minor * $this->quantity;
    }

    public function productThumbnail(): ?ProductImage
    {
        $images = $this->product?->images?->where('is_active', true)->sortBy('sort_order');
        if (! $images || $images->isEmpty()) {
            return null;
        }

        return $images->firstWhere('is_product', true)
            ?? $images->firstWhere('is_primary', true)
            ?? $images->first();
    }
}
