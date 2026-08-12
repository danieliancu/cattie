<?php

namespace App\Filament\Resources\FulfilmentProductMappings;

use App\Filament\Resources\FulfilmentProductMappings\Pages\CreateFulfilmentProductMapping;
use App\Filament\Resources\FulfilmentProductMappings\Pages\EditFulfilmentProductMapping;
use App\Filament\Resources\FulfilmentProductMappings\Pages\ListFulfilmentProductMappings;
use App\Filament\Resources\FulfilmentProductMappings\Schemas\FulfilmentProductMappingForm;
use App\Filament\Resources\FulfilmentProductMappings\Tables\FulfilmentProductMappingsTable;
use App\Models\FulfilmentProductMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FulfilmentProductMappingResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Provider Sync';

    protected static ?string $model = FulfilmentProductMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FulfilmentProductMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FulfilmentProductMappingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfilmentProductMappings::route('/'),
            'create' => CreateFulfilmentProductMapping::route('/create'),
            'edit' => EditFulfilmentProductMapping::route('/{record}/edit'),
        ];
    }
}
