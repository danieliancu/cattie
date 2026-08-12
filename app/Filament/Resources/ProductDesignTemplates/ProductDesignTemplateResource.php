<?php

namespace App\Filament\Resources\ProductDesignTemplates;

use App\Filament\Resources\ProductDesignTemplates\Pages\CreateProductDesignTemplate;
use App\Filament\Resources\ProductDesignTemplates\Pages\EditProductDesignTemplate;
use App\Filament\Resources\ProductDesignTemplates\Pages\ListProductDesignTemplates;
use App\Filament\Resources\ProductDesignTemplates\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\ProductDesignTemplates\Schemas\ProductDesignTemplateForm;
use App\Filament\Resources\ProductDesignTemplates\Tables\ProductDesignTemplatesTable;
use App\Models\ProductDesignTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductDesignTemplateResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Design';

    protected static ?string $model = ProductDesignTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProductDesignTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductDesignTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductDesignTemplates::route('/'),
            'create' => CreateProductDesignTemplate::route('/create'),
            'edit' => EditProductDesignTemplate::route('/{record}/edit'),
        ];
    }
}
