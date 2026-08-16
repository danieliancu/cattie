<?php

namespace App\Filament\Resources\OrderSupportRequests\Pages;

use App\Filament\Resources\OrderSupportRequests\OrderSupportRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderSupportRequests extends ListRecords
{
    protected static string $resource = OrderSupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
