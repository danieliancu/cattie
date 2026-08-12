<?php

namespace App\Filament\Resources\ProductDesignTemplates\Pages;

use App\Filament\Resources\ProductDesignTemplates\ProductDesignTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductDesignTemplate extends EditRecord
{
    protected static string $resource = ProductDesignTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
