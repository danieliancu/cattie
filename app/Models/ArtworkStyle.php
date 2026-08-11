<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtworkStyle extends Model
{
    use HasFactory, UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_active' => 'boolean'];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }
}
