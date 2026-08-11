<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class UploadAsset extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key'];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
