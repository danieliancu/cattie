<?php

namespace App\Filament\Resources\FulfilmentProductMappings\Tables;

use App\Jobs\SyncProviderVariant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FulfilmentProductMappingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('variant.product.name')->label('Product'),
                TextColumn::make('variant.name')->label('Variant'),
                TextColumn::make('provider')
                    ->searchable(),
                TextColumn::make('provider_sku')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_cost_minor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supplier_cost_currency')
                    ->searchable(),
                TextColumn::make('supplier_vat_basis')
                    ->searchable(),
                TextColumn::make('availability')
                    ->searchable(),
                TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_sync_status')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('sync')->action(fn ($record) => SyncProviderVariant::dispatch($record->id)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
