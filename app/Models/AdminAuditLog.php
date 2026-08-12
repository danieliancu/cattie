<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    use UsesUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
