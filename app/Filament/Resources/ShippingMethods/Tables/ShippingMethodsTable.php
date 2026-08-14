<?php

namespace App\Filament\Resources\ShippingMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('provider')->badge()->sortable(),
            TextColumn::make('provider_service_code')->label('Provider service')->searchable(),
            TextColumn::make('price_minor')->label('Price')->money('GBP', divideBy: 100)->sortable(),
            TextColumn::make('delivery_estimate')->label('Estimated delivery')->state(fn ($record) => $record->estimateLabel()),
            TextColumn::make('country')->badge(),
            IconColumn::make('is_active')->boolean()->sortable(),
            TextColumn::make('sort_order')->sortable(),
        ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
