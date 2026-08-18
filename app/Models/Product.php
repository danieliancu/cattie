<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\UsesUlids;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes, UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'status' => ProductStatus::class, 'artwork_requirements' => 'array', 'preview_configuration' => 'array'];
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_categories.sort_order')
            ->orderBy('product_categories.name');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function personalisationFields()
    {
        return $this->hasMany(ProductPersonalisationField::class)->orderBy('sort_order');
    }

    public function artworkStyles()
    {
        return $this->belongsToMany(ArtworkStyle::class)->where('artwork_styles.is_active', true);
    }

    public function recommendedArtworkStyle()
    {
        return $this->belongsTo(ArtworkStyle::class, 'recommended_artwork_style_id');
    }

    public function designTemplate()
    {
        return $this->belongsTo(ProductDesignTemplate::class, 'product_design_template_id');
    }

    public function defaultVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'default_variant_id');
    }

    public function designTemplateAssignments()
    {
        return $this->hasMany(DesignTemplateAssignment::class);
    }

    public function slugRedirects()
    {
        return $this->hasMany(ProductSlugRedirect::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published->value)->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function displayPriceMinor(): int
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->where('is_active', true)->get();

        return $variants->where('is_active', true)->map(fn ($variant) => $variant->price_override_minor ?? $variant->price_minor)->min() ?? $this->default_price_minor ?? $this->base_price_minor;
    }

    public function formattedPrice(): string
    {
        return Money::format($this->displayPriceMinor(), $this->currency);
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->relationLoaded('images') ? $this->images->where('is_active', true)->sortByDesc('is_primary')->first() : $this->images()->where('is_active', true)->orderByDesc('is_primary')->first();
    }

    public function usesStaticMockupBoundary(): bool
    {
        return ($this->preview_configuration['mockup_mode'] ?? null) === 'static_boundary';
    }

    /**
     * Whether the generated illustration keeps its own background (e.g. wall prints), rather
     * than having it cut away. Mirrors the background-removal gate in BackgroundRemovalProcessor,
     * which only strips the background when `transparent_background` is requested.
     */
    public function keepsPhotoBackground(): bool
    {
        return ! (bool) ($this->artwork_requirements['transparent_background'] ?? false);
    }

    public function previewMockupUrl(): ?string
    {
        $asset = $this->preview_configuration['mockup_asset'] ?? null;

        return isset($asset['disk'], $asset['storage_key'])
            ? Storage::disk($asset['disk'])->url($asset['storage_key'])
            : null;
    }
}
