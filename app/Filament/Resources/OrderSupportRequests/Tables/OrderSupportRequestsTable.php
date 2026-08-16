<?php

namespace App\Filament\Resources\OrderSupportRequests\Tables;

use App\Enums\OrderSupportStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderSupportRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('order.number')->label('Order')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('contact_email')->label('Contact email'),
            TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
        ])
            ->filters([
                SelectFilter::make('status')->options(OrderSupportStatus::class),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
