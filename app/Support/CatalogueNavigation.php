<?php

namespace App\Support;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The one query behind every taxonomy-driven surface: header navigation, the
 * homepage, the shop chip row, the footer and both sitemaps.
 *
 * Categories are intentionally NOT filtered by "has products". The new
 * structure has to be browsable before the products are imported, so an empty
 * category or subcategory still appears in navigation. Indexability is handled
 * separately (empty subcategories are noindex and stay out of sitemap.xml).
 */
class CatalogueNavigation
{
    private const COLUMNS = [
        'id', 'name', 'slug', 'parent_id', 'short_description', 'sort_order', 'updated_at',
        'image_disk', 'image_storage_key', 'image_alt_text',
    ];

    /**
     * Active top-level categories with their active children, correctly ordered.
     *
     * Three queries, no N+1. The column list must keep `id` and `parent_id`:
     * without `id` the children never hydrate, and without `parent_id` a child
     * cannot build its own nested URL.
     *
     * @return Collection<int, ProductCategory>
     */
    public static function topLevelWithChildren(): Collection
    {
        $tree = ProductCategory::query()
            ->select(self::COLUMNS)
            ->active()
            ->topLevel()
            ->with(['children' => fn ($query) => $query
                ->select(self::COLUMNS)
                ->active()
                ->ordered()])
            ->ordered()
            ->get()
            ->each(fn (ProductCategory $parent) => $parent->children
                // Back-link so ProductCategory::url() never needs its fallback query.
                ->each(fn (ProductCategory $child) => $child->setRelation('parent', $parent)));

        self::hydrateProductImages($tree);

        return $tree;
    }

    /**
     * Attaches up to $limit products to each top-level category in $tree, as
     * ->featuredProducts. Products hang off leaf subcategories, so a parent's
     * products are the union of its children's, deduplicated, in catalogue order.
     *
     * Two queries total, regardless of how many categories or products exist —
     * same batching shape as hydrateProductImages().
     *
     * @param  Collection<int, ProductCategory>  $tree
     * @return Collection<int, ProductCategory>
     */
    public static function attachFeaturedProducts(Collection $tree, int $limit = 8): Collection
    {
        // Map every child (and the parent itself, defensively) back to its top-level id.
        $parentOf = [];
        foreach ($tree as $parent) {
            $parentOf[$parent->getKey()] = $parent->getKey();
            foreach ($parent->children as $child) {
                $parentOf[$child->getKey()] = $parent->getKey();
            }
        }

        if ($parentOf === []) {
            return $tree;
        }

        $rows = DB::table('product_category')
            ->join('products', 'products.id', '=', 'product_category.product_id')
            ->whereIn('product_category.product_category_id', array_keys($parentOf))
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::Published->value)
            ->whereNull('products.deleted_at')
            ->orderBy('product_category.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.name')
            ->get(['product_category.product_category_id as category_id', 'product_category.product_id']);

        // Group into ordered, deduped product-id lists per top-level parent, capped at $limit.
        $idsByParent = [];
        foreach ($rows as $row) {
            $parentId = $parentOf[$row->category_id] ?? null;
            if ($parentId === null) {
                continue;
            }
            $idsByParent[$parentId] ??= [];
            if (count($idsByParent[$parentId]) < $limit && ! in_array($row->product_id, $idsByParent[$parentId], true)) {
                $idsByParent[$parentId][] = $row->product_id;
            }
        }

        $neededIds = array_values(array_unique(array_merge(...array_values($idsByParent) ?: [[]])));

        $productsById = $neededIds === []
            ? collect()
            : Product::query()
                ->whereIn('id', $neededIds)
                ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
                ->get()
                ->keyBy('id');

        foreach ($tree as $parent) {
            $ids = $idsByParent[$parent->getKey()] ?? [];
            $parent->setRelation(
                'featuredProducts',
                collect($ids)->map(fn ($id) => $productsById->get($id))->filter()->values()
            );
        }

        return $tree;
    }

    /**
     * Resolves, in one query, the product photo each category falls back to when
     * it has no image of its own.
     *
     * Without this, every rendered category would look up its own products, and a
     * top-level category would additionally search its children — an N+1 on a
     * fragment that appears on every storefront page.
     *
     * @param  Collection<int, ProductCategory>  $tree
     */
    private static function hydrateProductImages(Collection $tree): void
    {
        // flatMap() drops back to a base collection, so keys are taken explicitly
        // rather than via the Eloquent-only modelKeys().
        $ids = $tree
            ->flatMap(fn (ProductCategory $parent) => [$parent, ...$parent->children])
            ->map(fn (ProductCategory $category) => $category->getKey())
            ->all();

        if ($ids === []) {
            return;
        }

        $rows = DB::table('product_category')
            ->join('products', 'products.id', '=', 'product_category.product_id')
            ->join('product_images', 'product_images.product_id', '=', 'products.id')
            ->whereIn('product_category.product_category_id', $ids)
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::Published->value)
            ->whereNull('products.deleted_at')
            ->orderBy('products.sort_order')
            ->orderBy('products.name')
            ->orderBy('product_images.sort_order')
            ->get([
                'product_category.product_category_id as category_id',
                'product_images.disk as disk',
                'product_images.storage_key as storage_key',
                'product_images.alt_text as alt_text',
            ]);

        // First row wins per category, mirroring the catalogue's own image ordering.
        $byCategory = [];

        foreach ($rows as $row) {
            $byCategory[$row->category_id] ??= [
                'url' => Storage::disk($row->disk ?: 'public')->url($row->storage_key),
                'alt' => (string) $row->alt_text,
            ];
        }

        foreach ($tree as $parent) {
            foreach ($parent->children as $child) {
                $child->setProductImageFallback($byCategory[$child->getKey()] ?? null);
            }

            // Products hang off leaf subcategories, so a parent borrows from its children.
            $inherited = $parent->children
                ->map(fn (ProductCategory $child) => $byCategory[$child->getKey()] ?? null)
                ->first(fn (?array $image) => $image !== null);

            $parent->setProductImageFallback($byCategory[$parent->getKey()] ?? $inherited);
        }
    }
}
