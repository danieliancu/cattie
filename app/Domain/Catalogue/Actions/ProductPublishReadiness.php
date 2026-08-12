<?php

namespace App\Domain\Catalogue\Actions;

use App\Enums\DesignTemplateVersionStatus;
use App\Models\Product;

class ProductPublishReadiness
{
    public function handle(Product $product): array
    {
        $product->loadMissing(['variants.fulfilmentMappings', 'images', 'categories', 'personalisationFields', 'artworkStyles', 'designTemplateAssignments.version']);
        $personalised = $product->personalisationFields->isNotEmpty();
        $checks = [
            'name' => [filled($product->name), 'Product name'],
            'slug' => [filled($product->slug), 'Valid slug'],
            'price' => [(int) ($product->default_price_minor ?? $product->base_price_minor) > 0, 'Retail price'],
            'variants' => [$product->variants->where('is_active', true)->isNotEmpty(), 'Active variant'],
            'default_variant' => [$product->variants->where('is_active', true)->where('is_default', true)->count() === 1, 'Exactly one default variant'],
            'primary_image' => [$product->images->where('is_active', true)->where('is_primary', true)->isNotEmpty(), 'Primary image'],
            'seo' => [filled($product->meta_description), 'SEO description'],
            'categories' => [$product->categories->isNotEmpty(), 'Category'],
            'artwork_style' => [! $personalised || $product->artworkStyles->isNotEmpty(), 'Artwork style'],
            'provider_mapping' => [! $personalised || $product->variants->where('is_active', true)->every(fn ($v) => $v->fulfilmentMappings->where('is_active', true)->count() === 1), 'Provider mapping'],
            'print_area' => [! $personalised || $product->variants->where('is_active', true)->every(function ($v) {
                try {
                    $v->requiredPrintResolution();

                    return true;
                } catch (\Throwable) {
                    return false;
                }
            }), 'Valid print area'],
        ];
        if ($personalised) {
            $assignment = $product->designTemplateAssignments->firstWhere('is_active', true)?->version;
            $checks['template'] = [$assignment?->status === DesignTemplateVersionStatus::Published, 'Published template'];
            $checks['template_test'] = [$assignment?->last_test_render_status === 'succeeded', 'Successful template test render'];
        }

        return collect($checks)->map(fn ($v, $key) => ['key' => $key, 'label' => $v[1], 'passed' => $v[0], 'critical' => true])->values()->all();
    }

    public function ready(Product $product): bool
    {
        return collect($this->handle($product))->every('passed');
    }
}
