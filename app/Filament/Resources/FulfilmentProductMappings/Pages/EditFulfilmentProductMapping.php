<?php

namespace App\Filament\Resources\FulfilmentProductMappings\Pages;

use App\Filament\Resources\FulfilmentProductMappings\FulfilmentProductMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFulfilmentProductMapping extends EditRecord
{
    protected static string $resource = FulfilmentProductMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
