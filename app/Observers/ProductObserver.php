<?php

namespace App\Observers;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductSlugRedirect;

class ProductObserver
{
    public function created(Product $product): void
    {
        if (auth()->user()?->is_admin) {
            app(RecordAdminAudit::class)->handle('product.created', $product, [], $product->only(['name', 'slug', 'status']));
        }
    }

    public function saving(Product $product): void
    {
        if ($product->isDirty('status')) {
            $product->is_active = $product->status === ProductStatus::Published;
        } elseif ($product->isDirty('is_active')) {
            $product->status = $product->is_active ? ProductStatus::Published : ProductStatus::Archived;
        }
        if ($product->isDirty('default_price_minor') && $product->default_price_minor !== null) {
            $product->base_price_minor = $product->default_price_minor;
        }
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged('slug') && $product->getRawOriginal('status') === ProductStatus::Published->value) {
            ProductSlugRedirect::updateOrCreate(['old_slug' => $product->getOriginal('slug')], ['product_id' => $product->id]);
            ProductSlugRedirect::where('old_slug', $product->slug)->delete();
        }
        if (auth()->user()?->is_admin) {
            foreach (['default_price_minor' => 'product.price.changed', 'slug' => 'product.slug.changed', 'status' => 'product.status.changed'] as $field => $action) {
                if ($product->wasChanged($field)) {
                    app(RecordAdminAudit::class)->handle($action, $product, [$field => $product->getRawOriginal($field)], [$field => $product->getAttribute($field)]);
                }
            }
        }
    }
}
