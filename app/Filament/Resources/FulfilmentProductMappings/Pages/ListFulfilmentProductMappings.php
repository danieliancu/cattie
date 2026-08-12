<?php

namespace App\Filament\Resources\FulfilmentProductMappings\Pages;

use App\Filament\Resources\FulfilmentProductMappings\FulfilmentProductMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfilmentProductMappings extends ListRecords
{
    protected static string $resource = FulfilmentProductMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
