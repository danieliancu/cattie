<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PersonalisationFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'personalisationFields';

    public function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('key')->required(), TextInput::make('label')->required(), Select::make('type')->options(['text' => 'Text'])->required(), Toggle::make('is_required'), TextInput::make('validation_rules.max')->label('Maximum characters')->numeric()->minValue(1)->maxValue(255), TextInput::make('sort_order')->numeric()->default(0)]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('label'), TextColumn::make('key'), TextColumn::make('type'), TextColumn::make('validation_rules.max')->label('Max')])->headerActions([CreateAction::make()])->recordActions([EditAction::make()])->reorderable('sort_order');
    }
}
