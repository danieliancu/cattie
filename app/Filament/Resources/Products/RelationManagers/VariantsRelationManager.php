<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\DesignTemplateVersionStatus;
use App\Models\DesignTemplateVersion;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(), TextInput::make('sku')->required()->unique(ignoreRecord: true), KeyValue::make('options')->required(),
            TextInput::make('price_override_minor')->label('Retail override (pence)')->numeric(), TextInput::make('price_minor')->label('Legacy price (pence)')->numeric()->required(),
            Toggle::make('is_active')->default(true), Toggle::make('is_default'), TextInput::make('sort_order')->numeric()->default(0),
            Repeater::make('fulfilmentMappings')->relationship()->schema([
                TextInput::make('provider')->required(), TextInput::make('provider_sku')->required(), Toggle::make('is_active')->default(true),
                TextInput::make('supplier_cost_minor')->label('Supplier cost (pence)')->numeric(), TextInput::make('supplier_cost_currency')->default('GBP'),
                KeyValue::make('configuration')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            Repeater::make('designTemplateAssignments')->label('Template override')->relationship()->maxItems(1)->schema([
                Select::make('design_template_version_id')->options(DesignTemplateVersion::with('template')->where('status', DesignTemplateVersionStatus::Published)->get()->mapWithKeys(fn ($v) => [$v->id => $v->template->name.' v'.$v->version]))->required(), Toggle::make('is_active')->default(true),
            ])->columns(2)->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        $syncDefault = function ($record) {
            if ($record->is_default) {
                $record->product->variants()->whereKeyNot($record->id)->update(['is_default' => false]);
                $record->product->update(['default_variant_id' => $record->id]);
            }
        };

        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('name'), TextColumn::make('sku'), TextColumn::make('price_override_minor')->money('GBP', divideBy: 100), IconColumn::make('is_default')->boolean(), IconColumn::make('is_active')->boolean(),
        ])->headerActions([CreateAction::make()->after($syncDefault)])->recordActions([EditAction::make()->after($syncDefault)])->reorderable('sort_order');
    }
}
