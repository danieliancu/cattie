<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes, UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fulfilmentMappings()
    {
        return $this->hasMany(FulfilmentProductMapping::class);
    }

    public function designTemplateAssignments()
    {
        return $this->hasMany(DesignTemplateAssignment::class, 'product_variant_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price_override_minor ?? $this->price_minor, $this->currency);
    }

    /** @return array{width: int, height: int} */
    public function requiredPrintResolution(string $printArea = 'default'): array
    {
        $mappings = $this->fulfilmentMappings()->where('is_active', true)->get();

        if ($mappings->count() !== 1) {
            throw new DomainException("Product variant [{$this->id}] must have exactly one active fulfilment mapping; [{$mappings->count()}] found.");
        }

        return $mappings->first()->requiredPrintResolution($printArea);
    }
}
