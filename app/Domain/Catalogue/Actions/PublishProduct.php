<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Enums\ProductStatus;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishProduct
{
    public function __construct(private ProductPublishReadiness $readiness, private RecordAdminAudit $audit) {}

    public function handle(Product $product): Product
    {
        $failed = collect($this->readiness->handle($product))->where('passed', false);
        if ($failed->isNotEmpty()) {
            throw new DomainException('Product is not ready: '.$failed->pluck('label')->join(', '));
        }

        return DB::transaction(function () use ($product) {
            $before = $product->only(['status', 'is_active']);
            $product->update(['status' => ProductStatus::Published, 'is_active' => true]);
            $this->audit->handle('product.published', $product, $before, $product->only(['status', 'is_active']));

            return $product->refresh();
        });
    }
}
