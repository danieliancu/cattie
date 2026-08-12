<?php

namespace App\Filament\Resources\ProductDesignTemplates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductDesignTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('key')
                    ->required(),
                TextInput::make('version')
                    ->required()
                    ->numeric(),
                TextInput::make('definition_path')
                    ->disabled(),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
