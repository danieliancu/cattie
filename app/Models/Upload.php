<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory, UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key', 'guest_token'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }

    public function artworkSession()
    {
        return $this->belongsTo(ArtworkSession::class);
    }

    public function assets()
    {
        return $this->hasMany(UploadAsset::class);
    }
}
