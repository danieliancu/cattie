<?php

namespace App\Filament\Resources\OrderSupportRequests\Pages;

use App\Filament\Resources\OrderSupportRequests\OrderSupportRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditOrderSupportRequest extends EditRecord
{
    protected static string $resource = OrderSupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPhoto')
                ->label('View photo')
                ->icon('heroicon-o-photo')
                ->visible(fn () => (bool) $this->record->photo_storage_key)
                ->url(fn () => route('admin.order-support.photo', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
