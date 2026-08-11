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
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fulfilmentMappings()
    {
        return $this->hasMany(FulfilmentProductMapping::class);
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
        return Money::format($this->price_minor, $this->currency);
    }

    /** @return array{width: int, height: int} */
    public function requiredPrintResolution(string $provider, string $printArea = 'default'): array
    {
        $mapping = $this->fulfilmentMappings()->where('provider', $provider)->where('is_active', true)->first();

        if (! $mapping) {
            throw new DomainException("Active fulfilment mapping [{$provider}] is missing for product variant [{$this->id}].");
        }

        return $mapping->requiredPrintResolution($printArea);
    }
}
