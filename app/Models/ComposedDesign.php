<?php

namespace App\Models;

use App\Enums\ComposedDesignStatus;
use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;

class ComposedDesign extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected $hidden = ['storage_key', 'preview_storage_key', 'editor_background_storage_key', 'personalisation_snapshot'];

    protected function casts(): array
    {
        return [
            'status' => ComposedDesignStatus::class,
            'personalisation_snapshot' => 'array',
            'character_adjustments' => 'array',
            'template_version' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function artworkSession()
    {
        return $this->belongsTo(ArtworkSession::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function generationAsset()
    {
        return $this->belongsTo(GenerationAsset::class);
    }

    public function designTemplate()
    {
        return $this->belongsTo(ProductDesignTemplate::class, 'product_design_template_id');
    }

    public function designTemplateVersion()
    {
        return $this->belongsTo(DesignTemplateVersion::class);
    }
}
