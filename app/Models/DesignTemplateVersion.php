<?php

namespace App\Models;

use App\Enums\DesignTemplateVersionStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class DesignTemplateVersion extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'status' => DesignTemplateVersionStatus::class, 'published_at' => 'datetime', 'last_test_render_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getRawOriginal('status') === DesignTemplateVersionStatus::Published->value
                && ! ($version->isDirty('status') && $version->status === DesignTemplateVersionStatus::Retired && count($version->getDirty()) === 1)) {
                throw new LogicException('Published template versions are immutable. Create a new draft version.');
            }
        });
    }

    public function template()
    {
        return $this->belongsTo(ProductDesignTemplate::class, 'product_design_template_id');
    }

    public function assignments()
    {
        return $this->hasMany(DesignTemplateAssignment::class);
    }
}
