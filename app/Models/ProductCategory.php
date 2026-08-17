<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductCategory extends Model
{
    use HasFactory, UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_category')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_category.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.name');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcategories(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * The canonical storefront URL for this category.
     *
     * Single source of truth — no Blade template or controller should build a
     * category path by hand.
     */
    public function url(): string
    {
        return static::urlFor($this->isTopLevel() ? null : $this->parentSlug(), $this->slug);
    }

    /**
     * Same URL rules, without needing a persisted model. Used by the admin's
     * read-only URL preview, which has to render before the record exists.
     */
    public static function urlFor(?string $parentSlug, string $slug): string
    {
        return $parentSlug === null
            ? route('catalogue.category', ['categorySlug' => $slug])
            : route('catalogue.subcategory', ['categorySlug' => $parentSlug, 'subcategorySlug' => $slug]);
    }

    /**
     * Card artwork for this category, as [url, alt].
     *
     * Falls back to the first active product's primary image so a collection
     * looks finished before anyone uploads dedicated marketing artwork. Returns
     * null when there is nothing real to show — callers render a neutral block
     * rather than a broken image.
     *
     * @return array{url: string, alt: string}|null
     */
    /**
     * Pre-resolved product fallback, injected when a whole tree is loaded at once
     * so that rendering navigation never queries per category.
     *
     * @see \App\Support\CatalogueNavigation::hydrateProductImages()
     */
    protected ?array $productImageFallback = null;

    protected bool $productImageFallbackResolved = false;

    /**
     * @param  array{url: string, alt: string}|null  $image
     */
    public function setProductImageFallback(?array $image): static
    {
        $this->productImageFallback = $image;
        $this->productImageFallbackResolved = true;

        return $this;
    }

    public function cardImage(): ?array
    {
        // 1. Whatever the admin explicitly uploaded always wins.
        if (filled($this->image_storage_key)) {
            return [
                'url' => Storage::disk($this->image_disk ?: 'public')->url($this->image_storage_key),
                'alt' => (string) ($this->image_alt_text ?? ''),
            ];
        }

        // 2. A real product photo is the most suggestive thing available. Products
        //    hang off leaf subcategories, so a top-level category looks into its
        //    children as well as itself.
        if ($this->productImageFallbackResolved) {
            if ($this->productImageFallback !== null) {
                return $this->productImageFallback;
            }
        } else {
            $image = $this->firstProductImage() ?? ($this->isTopLevel() ? $this->firstDescendantProductImage() : null);

            if ($image) {
                return ['url' => $image->url(), 'alt' => (string) $image->alt_text];
            }
        }

        // 3. The generated brand texture, if one was published for this slug.
        $texture = 'categories/'.$this->slug.'.jpg';

        if (Storage::disk('public')->exists($texture)) {
            return ['url' => Storage::disk('public')->url($texture), 'alt' => ''];
        }

        return null;
    }

    private function firstProductImage(): ?ProductImage
    {
        return $this->products()->active()->with('images')->first()?->primaryImage();
    }

    private function firstDescendantProductImage(): ?ProductImage
    {
        return Product::query()->active()
            ->whereHas('categories', fn ($query) => $query->where('parent_id', $this->getKey()))
            ->ordered()
            ->with('images')
            ->first()?->primaryImage();
    }

    /**
     * Prefers an eager-loaded parent so rendering a full navigation tree costs
     * no extra queries.
     */
    protected function parentSlug(): ?string
    {
        if ($this->relationLoaded('parent')) {
            return $this->getRelation('parent')?->slug;
        }

        return static::query()->whereKey($this->parent_id)->value('slug');
    }
}
