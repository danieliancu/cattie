<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class GenerationAsset extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean', 'selected_at' => 'datetime'];
    }

    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }

    public function composedDesigns()
    {
        return $this->hasMany(ComposedDesign::class);
    }
}
