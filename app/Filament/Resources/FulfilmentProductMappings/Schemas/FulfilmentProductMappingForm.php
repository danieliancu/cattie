<?php

namespace App\Filament\Resources\FulfilmentProductMappings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FulfilmentProductMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_variant_id')
                    ->required(),
                TextInput::make('provider')
                    ->required(),
                TextInput::make('provider_sku')
                    ->required(),
                Textarea::make('configuration')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('supplier_cost_minor')
                    ->numeric(),
                TextInput::make('supplier_cost_currency'),
                TextInput::make('supplier_vat_basis'),
                TextInput::make('availability'),
                DateTimePicker::make('last_synced_at'),
                TextInput::make('last_sync_status'),
                Textarea::make('last_sync_error')
                    ->columnSpanFull(),
            ]);
    }
}
