<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(), TextColumn::make('status')->badge(),
            TextColumn::make('default_price_minor')->label('Retail')->money('GBP', divideBy: 100)->sortable(),
            TextColumn::make('variants_count')->counts('variants')->label('Variants'), TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()])->defaultSort('sort_order');
    }
}
