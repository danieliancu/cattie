<?php

namespace App\Observers;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Models\FulfilmentProductMapping;

class FulfilmentProductMappingObserver
{
    public function updated(FulfilmentProductMapping $mapping): void
    {
        if (! auth()->user()?->is_admin) {
            return;
        }
        if ($mapping->wasChanged('provider_sku')) {
            app(RecordAdminAudit::class)->handle('provider.sku.changed', $mapping, ['provider_sku' => $mapping->getRawOriginal('provider_sku')], ['provider_sku' => $mapping->provider_sku]);
        }
        if ($mapping->wasChanged('configuration')) {
            app(RecordAdminAudit::class)->handle('provider.print_area.changed', $mapping, [], ['configuration' => $mapping->configuration]);
        }
    }
}
