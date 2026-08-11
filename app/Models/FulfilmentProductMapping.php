<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use DomainException;
use Illuminate\Database\Eloquent\Model;

class FulfilmentProductMapping extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_active' => 'boolean'];
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return array{width: int, height: int} */
    public function requiredPrintResolution(string $printArea = 'default'): array
    {
        $resolution = $this->configuration['print_areas'][$printArea] ?? null;
        $width = filter_var($resolution['width'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $height = filter_var($resolution['height'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($width === false || $height === false) {
            throw new DomainException("Required print resolution [{$printArea}] is missing for fulfilment mapping [{$this->id}].");
        }

        return ['width' => $width, 'height' => $height];
    }
}
