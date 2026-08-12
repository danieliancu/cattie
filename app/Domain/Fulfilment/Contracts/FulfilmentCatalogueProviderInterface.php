<?php

namespace App\Domain\Fulfilment\Contracts;

use App\Models\FulfilmentProductMapping;

interface FulfilmentCatalogueProviderInterface
{
    public function capabilities(): array;

    public function sync(FulfilmentProductMapping $mapping): array;
}
