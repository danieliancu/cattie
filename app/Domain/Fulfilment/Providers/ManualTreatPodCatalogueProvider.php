<?php

namespace App\Domain\Fulfilment\Providers;

use App\Domain\Fulfilment\Contracts\FulfilmentCatalogueProviderInterface;
use App\Models\FulfilmentProductMapping;
use DomainException;

class ManualTreatPodCatalogueProvider implements FulfilmentCatalogueProviderInterface
{
    public function capabilities(): array
    {
        return ['variant_lookup' => false, 'cost_sync' => false, 'availability_sync' => false];
    }

    public function sync(FulfilmentProductMapping $mapping): array
    {
        throw new DomainException('TreatPod catalogue sync is manual until a documented API is configured.');
    }
}
