<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    use HasFactory, UsesUlids;

    protected $guarded = [];

    protected $hidden = ['resolved_prompt'];

    protected function casts(): array
    {
        return ['status' => GenerationStatus::class, 'parameters' => 'array', 'usage_metadata' => 'array', 'is_retryable' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    public function savedCharacter()
    {
        return $this->belongsTo(SavedCharacter::class);
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

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_generation_id');
    }

    public function regenerations()
    {
        return $this->hasMany(self::class, 'parent_generation_id');
    }

    public function assets()
    {
        return $this->hasMany(GenerationAsset::class);
    }

    public function artworkSession()
    {
        return $this->belongsTo(ArtworkSession::class);
    }
}
