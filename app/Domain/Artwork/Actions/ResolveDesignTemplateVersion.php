<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\DesignTemplateVersionStatus;
use App\Models\DesignTemplateVersion;
use App\Models\Product;
use App\Models\ProductVariant;

class ResolveDesignTemplateVersion
{
    public function handle(Product $product, ProductVariant $variant): ?DesignTemplateVersion
    {
        return DesignTemplateVersion::query()->where('status', DesignTemplateVersionStatus::Published->value)
            ->whereHas('assignments', fn ($q) => $q->where('is_active', true)->where('product_variant_id', $variant->id))->latest('version')->first()
            ?? DesignTemplateVersion::query()->where('status', DesignTemplateVersionStatus::Published->value)
                ->whereHas('assignments', fn ($q) => $q->where('is_active', true)->where('product_id', $product->id)->whereNull('product_variant_id'))->latest('version')->first();
    }
}
