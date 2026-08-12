<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Catalogue\Actions\DuplicateProduct;
use App\Domain\Catalogue\Actions\PublishProduct;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\URL;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->url(fn () => URL::temporarySignedRoute('admin.products.preview', now()->addMinutes(30), ['product' => $this->record]))->openUrlInNewTab(),
            Action::make('publish')->color('success')->action(function () {
                try {
                    app(PublishProduct::class)->handle($this->record);
                    Notification::make()->title('Product published')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Not ready')->body($e->getMessage())->danger()->send();
                }
            }),
            Action::make('duplicate')->action(function () {
                $copy = app(DuplicateProduct::class)->handle($this->record);
                $this->redirect(ProductResource::getUrl('edit', ['record' => $copy]));
            }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
