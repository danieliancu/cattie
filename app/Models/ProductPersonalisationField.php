<?php

namespace App\Models;

use App\Enums\PersonalisationFieldType;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class ProductPersonalisationField extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => PersonalisationFieldType::class, 'is_required' => 'boolean', 'validation_rules' => 'array', 'configuration' => 'array'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
