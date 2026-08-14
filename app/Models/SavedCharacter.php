<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class SavedCharacter extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key', 'preview_storage_key', 'sha256'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function artworkStyle()
    {
        return $this->belongsTo(ArtworkStyle::class);
    }

    public function sourceAsset()
    {
        return $this->belongsTo(GenerationAsset::class, 'source_generation_asset_id');
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }
}
