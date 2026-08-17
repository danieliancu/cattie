<?php

namespace App\Observers;

use App\Exceptions\InvalidCategoryHierarchyException as Invalid;
use App\Models\ProductCategory;
use App\Support\ReservedSlugs;

/**
 * Safety backstop for the category hierarchy invariants.
 *
 * User-facing validation lives in the Filament form, which rejects invalid
 * input with proper inline field errors before anything is saved. This observer
 * exists so the same rules also hold for seeders, tinker, CLI commands and
 * future domain actions, which never go through that form.
 */
class ProductCategoryObserver
{
    public function saving(ProductCategory $category): void
    {
        if ($category->isDirty('slug')) {
            $slug = (string) $category->slug;

            if (preg_match('/^'.ReservedSlugs::SLUG_PATTERN.'$/', $slug) !== 1) {
                throw Invalid::invalidSlug($slug);
            }
        }

        if ($category->parent_id === null) {
            // Only top-level categories own a first URL segment.
            if ($category->isDirty('slug') && ReservedSlugs::has((string) $category->slug)) {
                throw Invalid::reservedSlug($category->slug);
            }

            return;
        }

        if ($category->exists && $category->parent_id === $category->getKey()) {
            throw Invalid::selfParent();
        }

        $parent = ProductCategory::query()->whereKey($category->parent_id)->first(['id', 'name', 'parent_id']);

        if ($parent === null) {
            throw Invalid::missingParent((string) $category->parent_id);
        }

        // Maximum depth is exactly two: Category -> Subcategory.
        if ($parent->parent_id !== null) {
            throw Invalid::depthExceeded($parent->name);
        }

        if ($category->exists && $category->children()->exists()) {
            throw Invalid::parentHasChildren($category->name);
        }
    }

    public function deleting(ProductCategory $category): void
    {
        if ($category->children()->exists()) {
            throw Invalid::hasChildren($category->name);
        }
    }
}
