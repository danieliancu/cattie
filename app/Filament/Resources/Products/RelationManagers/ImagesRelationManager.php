<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('storage_key')->image()->disk('public')->directory(fn () => 'admin-catalogue/'.$this->getOwnerRecord()->slug)->maxSize(10240)->required(),
            Select::make('product_variant_id')->label('Variant')->options(fn () => $this->getOwnerRecord()->variants()->pluck('name', 'id'))->nullable(),
            Select::make('role')->options(array_combine(['primary', 'gallery', 'school', 'home', 'outdoor', 'detail'], ['Primary', 'Gallery', 'School', 'Home', 'Outdoor', 'Detail']))->required(),
            TextInput::make('alt_text')->required(), Toggle::make('is_primary')->label('Primary'), Toggle::make('is_product')->label('Product'), Toggle::make('is_active')->default(true), TextInput::make('sort_order')->numeric()->default(0),
            TextInput::make('disk')->default('public')->hidden(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $syncPrimary = function ($record) {
            if ($record->is_primary) {
                $record->product->images()->whereKeyNot($record->id)->update(['is_primary' => false]);
            }
        };
        $syncProduct = function ($record) {
            if ($record->is_product) {
                $record->product->images()->whereKeyNot($record->id)->update(['is_product' => false]);
            }
        };
        $syncFlags = function ($record) use ($syncPrimary, $syncProduct) {
            $syncPrimary($record);
            $syncProduct($record);
        };

        return $table->columns([
            ImageColumn::make('storage_key')->disk('public'),
            TextColumn::make('alt_text'),
            TextColumn::make('variant.name'),
            TextColumn::make('role'),
            ToggleColumn::make('is_primary')->label('Primary')->afterStateUpdated(fn ($record) => $syncPrimary($record)),
            ToggleColumn::make('is_product')->label('Product')->afterStateUpdated(fn ($record) => $syncProduct($record)),
        ])->headerActions([CreateAction::make()->after($syncFlags)])->recordActions([EditAction::make()->after($syncFlags), DeleteAction::make()])->reorderable('sort_order');
    }
}
