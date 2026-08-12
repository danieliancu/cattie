<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Domain\Catalogue\Actions\ProductPublishReadiness;
use App\Enums\ProductStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Commerce')->schema([
                TextInput::make('name')->required()->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Select::make('status')->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => Str::headline($s->value)]))->default(ProductStatus::Draft->value)->required(),
                TextInput::make('default_price_minor')->label('Default retail price (pence)')->numeric()->required()->minValue(1),
                TextInput::make('currency')->default('GBP')->required()->maxLength(3),
                TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2)->columnSpanFull(),
            Section::make('Content and SEO')->schema([
                Textarea::make('short_description')->rows(2), Textarea::make('description')->required()->rows(6),
                TextInput::make('meta_title')->maxLength(255), Textarea::make('meta_description')->maxLength(160),
            ])->columns(2)->columnSpanFull(),
            Section::make('Classification and personalisation')->schema([
                Select::make('categories')->relationship('categories', 'name')->multiple()->preload(),
                Select::make('artworkStyles')->relationship('artworkStyles', 'name')->multiple()->preload(),
                Select::make('recommended_artwork_style_id')->relationship('recommendedArtworkStyle', 'name'),
                KeyValue::make('preview_configuration')->keyLabel('Setting')->valueLabel('Value'),
                KeyValue::make('artwork_requirements')->keyLabel('Requirement')->valueLabel('Value'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Publish readiness')->schema([
                Placeholder::make('readiness')->label('Checks')->content(fn ($record) => $record ? collect(app(ProductPublishReadiness::class)->handle($record))->map(fn ($check) => ($check['passed'] ? '✓ ' : '✗ ').$check['label'])->join("\n") : 'Save the draft to run readiness checks.'),
            ])->columnSpanFull(),
        ]);
    }
}
