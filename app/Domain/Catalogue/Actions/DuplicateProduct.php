<?php

namespace App\Domain\Catalogue\Actions;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateProduct
{
    public function handle(Product $source, bool $categories = true, bool $variants = true, bool $assignment = true): Product
    {
        return DB::transaction(function () use ($source, $categories, $variants, $assignment) {
            $copy = $source->replicate(['slug', 'status', 'is_active', 'default_variant_id']);
            $copy->name = $source->name.' Copy';
            $copy->slug = Str::slug($copy->name).'-'.Str::lower(Str::random(5));
            $copy->status = ProductStatus::Draft;
            $copy->is_active = false;
            $copy->save();
            $copy->artworkStyles()->sync($source->artworkStyles()->pluck('artwork_styles.id'));
            if ($categories) {
                $copy->categories()->sync($source->categories()->pluck('product_categories.id'));
            }
            foreach ($source->personalisationFields as $field) {
                $copy->personalisationFields()->create($field->only(['key', 'label', 'type', 'is_required', 'validation_rules', 'configuration', 'sort_order']));
            }
            $variantMap = [];
            if ($variants) {
                foreach ($source->variants as $index => $variant) {
                    $new = $copy->variants()->create([...$variant->only(['name', 'options', 'price_minor', 'price_override_minor', 'currency', 'is_active', 'sort_order', 'is_default']), 'sku' => 'COPY-'.Str::upper(Str::random(6)).'-'.$index]);
                    $variantMap[$variant->id] = $new->id;
                    foreach ($variant->fulfilmentMappings as $mapping) {
                        $new->fulfilmentMappings()->create([...$mapping->only(['provider', 'configuration', 'supplier_cost_minor', 'supplier_cost_currency', 'supplier_vat_basis']), 'provider_sku' => '', 'is_active' => false]);
                    }
                    if ($variant->is_default) {
                        $copy->update(['default_variant_id' => $new->id]);
                    }
                }
            }
            foreach ($source->images as $image) {
                $copy->images()->create([...$image->only(['disk', 'storage_key', 'alt_text', 'sort_order', 'role', 'is_primary', 'is_active']), 'product_variant_id' => $image->product_variant_id ? ($variantMap[$image->product_variant_id] ?? null) : null]);
            }
            if ($assignment && $source->designTemplateAssignments()->where('is_active', true)->first()) {
                $copy->designTemplateAssignments()->create(['design_template_version_id' => $source->designTemplateAssignments()->where('is_active', true)->first()->design_template_version_id, 'is_active' => true]);
            }

            return $copy;
        });
    }
}
