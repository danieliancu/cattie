<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Models\ProductCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent:id,name,slug')->withCount('products'))
            // Sorts subcategories directly beneath their own parent.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('COALESCE((select p.sort_order from product_categories p where p.id = product_categories.parent_id), product_categories.sort_order)')
                ->orderByRaw('case when parent_id is null then 0 else 1 end')
                ->orderBy('sort_order')
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ProductCategory $record): ?string => $record->parent?->name)
                    // Indents children so the hierarchy is readable at a glance.
                    ->formatStateUsing(fn (ProductCategory $record, string $state): string => $record->isTopLevel() ? $state : '— '.$state),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->state(fn (ProductCategory $record): string => $record->isTopLevel() ? 'Category' : 'Subcategory')
                    ->color(fn (string $state): string => $state === 'Category' ? 'primary' : 'gray'),
                TextColumn::make('parent.name')->label('Parent')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('path')
                    ->label('URL')
                    ->state(fn (ProductCategory $record): string => parse_url($record->url(), PHP_URL_PATH) ?: '/')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('products_count')->label('Products')->numeric()->sortable(),
                TextColumn::make('sort_order')->numeric()->sortable(),
                IconColumn::make('is_active')->label('Published')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('top_level')->label('Top-level categories')
                    ->query(fn (Builder $query) => $query->whereNull('parent_id')),
                Filter::make('subcategories')->label('Subcategories')
                    ->query(fn (Builder $query) => $query->whereNotNull('parent_id')),
                TernaryFilter::make('is_active')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->before(function (ProductCategory $record, DeleteAction $action) {
                    if ($record->children()->exists()) {
                        Notification::make()->danger()
                            ->title('Cannot delete this category')
                            ->body('Remove or re-parent its subcategories first.')
                            ->send();
                        $action->cancel();
                    }
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
