<?php

namespace App\Jobs;

use App\Domain\Fulfilment\Providers\ManualTreatPodCatalogueProvider;
use App\Models\FulfilmentProductMapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncProviderVariant implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $mappingId) {}

    public function handle(ManualTreatPodCatalogueProvider $provider): void
    {
        $mapping = FulfilmentProductMapping::findOrFail($this->mappingId);
        try {
            $provider->sync($mapping);
            $mapping->update(['last_synced_at' => now(), 'last_sync_status' => 'succeeded', 'last_sync_error' => null]);
        } catch (Throwable $e) {
            $mapping->update(['last_synced_at' => now(), 'last_sync_status' => 'failed', 'last_sync_error' => $e->getMessage()]);
        }
    }
}
