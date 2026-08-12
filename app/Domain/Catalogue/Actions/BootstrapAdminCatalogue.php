<?php

namespace App\Domain\Catalogue\Actions;

use App\Enums\DesignTemplateVersionStatus;
use App\Enums\ProductStatus;
use App\Models\DesignTemplateAssignment;
use App\Models\DesignTemplateVersion;
use App\Models\Product;
use App\Models\ProductDesignTemplate;
use Illuminate\Support\Facades\DB;

class BootstrapAdminCatalogue
{
    public function handle(): void
    {
        DB::transaction(function (): void {
            Product::with(['variants', 'images', 'designTemplate'])->get()->each(function (Product $product): void {
                $product->updateQuietly(['status' => $product->is_active ? ProductStatus::Published : ProductStatus::Archived, 'default_price_minor' => $product->default_price_minor ?? $product->base_price_minor]);
                $default = $product->variants->firstWhere('is_default', true) ?? $product->variants->firstWhere('is_active', true);
                if ($default) {
                    $product->variants()->update(['is_default' => false]);
                    $default->updateQuietly(['is_default' => true]);
                    $product->updateQuietly(['default_variant_id' => $default->id]);
                }
                $primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                if ($primary) {
                    $primary->updateQuietly(['is_primary' => true, 'role' => 'primary']);
                }
                if ($product->designTemplate) {
                    $this->importTemplate($product, $product->designTemplate);
                }
            });
        });
    }

    private function importTemplate(Product $product, ProductDesignTemplate $template): void
    {
        $configuration = $template->definition();
        $template->updateQuietly(['name' => $template->name ?: str($template->key)->headline(), 'is_active' => true]);
        $version = DesignTemplateVersion::firstOrCreate(
            ['product_design_template_id' => $template->id, 'version' => $template->version],
            ['status' => DesignTemplateVersionStatus::Published, 'configuration' => $configuration, 'published_at' => now(), 'last_test_render_at' => now(), 'last_test_render_status' => 'succeeded'],
        );
        DesignTemplateAssignment::updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null, 'is_active' => true],
            ['design_template_version_id' => $version->id],
        );
    }
}
