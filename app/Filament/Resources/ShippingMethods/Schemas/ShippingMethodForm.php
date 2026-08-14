<?php

namespace App\Filament\Resources\ShippingMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider')->options(['treatpod' => 'TreatPod', 'prodigi' => 'Prodigi'])->required(),
            TextInput::make('code')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(150),
            TextInput::make('provider_service_code')->required()->maxLength(150),
            TextInput::make('price_minor')->label('Price (pence)')->numeric()->integer()->minValue(0)->required(),
            Select::make('currency')->options(['GBP' => 'GBP'])->default('GBP')->required(),
            Select::make('country')->options(['GB' => 'United Kingdom'])->default('GB')->required(),
            TextInput::make('estimated_business_days_min')->label('Minimum business days')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('estimated_business_days_max')->label('Maximum business days')->numeric()->integer()->minValue(1)->gte('estimated_business_days_min')->required(),
            TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->default(true)->required(),
        ]);
    }
}
