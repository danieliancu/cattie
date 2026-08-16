<?php

namespace App\Filament\Resources\OrderSupportRequests;

use App\Filament\Resources\OrderSupportRequests\Pages\EditOrderSupportRequest;
use App\Filament\Resources\OrderSupportRequests\Pages\ListOrderSupportRequests;
use App\Filament\Resources\OrderSupportRequests\Schemas\OrderSupportRequestForm;
use App\Filament\Resources\OrderSupportRequests\Tables\OrderSupportRequestsTable;
use App\Models\OrderSupportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderSupportRequestResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Customer Support';

    protected static ?string $navigationLabel = 'Order Support';

    protected static ?string $model = OrderSupportRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    public static function form(Schema $schema): Schema
    {
        return OrderSupportRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderSupportRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderSupportRequests::route('/'),
            'edit' => EditOrderSupportRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
