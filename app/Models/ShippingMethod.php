<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingMethod extends Model
{
    use SoftDeletes, UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('price_minor')->orderBy('sort_order')->orderBy('name');
    }

    public function estimateLabel(): string
    {
        return $this->estimated_business_days_min === $this->estimated_business_days_max
            ? $this->estimated_business_days_min.' business days'
            : $this->estimated_business_days_min.'–'.$this->estimated_business_days_max.' business days';
    }

    public function snapshot(): array
    {
        return [
            'shipping_method_id' => $this->id,
            'provider' => $this->provider,
            'code' => $this->code,
            'name' => $this->name,
            'provider_service_code' => $this->provider_service_code,
            'price_minor' => $this->price_minor,
            'currency' => $this->currency,
            'country' => $this->country,
            'estimated_business_days_min' => $this->estimated_business_days_min,
            'estimated_business_days_max' => $this->estimated_business_days_max,
        ];
    }
}
