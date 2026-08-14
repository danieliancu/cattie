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
            'resolved_manifest' => 'array',
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

    public function previewSurfaceColour(): string
    {
        $surface = $this->resolved_manifest['configuration']['preview_surface'] ?? [];
        if ($option = $surface['variant_option'] ?? null) {
            $value = mb_strtolower(trim((string) ($this->variant?->options[$option] ?? '')));

            return $surface['colours_by_variant'][$value] ?? $surface['fallback_colour'] ?? '#f4efe7';
        }

        return $surface['colour'] ?? '#f4efe7';
    }
}
