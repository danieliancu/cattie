<?php

namespace App\Filament\Resources\ProductDesignTemplates\Pages;

use App\Filament\Resources\ProductDesignTemplates\ProductDesignTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductDesignTemplates extends ListRecords
{
    protected static string $resource = ProductDesignTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
