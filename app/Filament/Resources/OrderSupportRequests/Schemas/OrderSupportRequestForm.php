<?php

namespace App\Filament\Resources\OrderSupportRequests\Schemas;

use App\Enums\OrderSupportStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderSupportRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->disabled()->dehydrated(false),
            Select::make('status')->options(OrderSupportStatus::class)->required(),
            TextInput::make('order.number')->label('Order number')->disabled()->dehydrated(false),
            TextInput::make('order.status')->label('Order status')->formatStateUsing(fn ($state) => $state?->customerLabel())->disabled()->dehydrated(false),
            TextInput::make('contact_email')->label('Contact email')->disabled()->dehydrated(false),
            TextInput::make('created_at')->label('Submitted')->disabled()->dehydrated(false),
            Textarea::make('message')->label('What went wrong?')->rows(6)->disabled()->dehydrated(false)->columnSpanFull(),
        ]);
    }
}
