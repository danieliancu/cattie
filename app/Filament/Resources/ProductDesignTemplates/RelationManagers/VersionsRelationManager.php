<?php

namespace App\Filament\Resources\ProductDesignTemplates\RelationManagers;

use App\Domain\Artwork\Actions\PublishDesignTemplateVersion;
use App\Domain\Artwork\Actions\TestRenderDesignTemplateVersion;
use App\Enums\DesignTemplateVersionStatus;
use App\Models\ProductVariant;
use App\Support\DesignFontRegistry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version')->numeric()->required(), Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'])->default('draft')->disabled(fn ($record) => $record?->status === DesignTemplateVersionStatus::Published),
            Select::make('configuration.layers.0.type')->label('Canvas type')->options(['solid' => 'Solid', 'transparent' => 'Transparent'])->required(), ColorPicker::make('configuration.layers.0.colour')->label('Canvas colour'),
            TextInput::make('configuration.character.x')->numeric()->minValue(0)->maxValue(1), TextInput::make('configuration.character.y')->numeric()->minValue(0)->maxValue(1), TextInput::make('configuration.character.scale')->numeric()->minValue(.1)->maxValue(2),
            Select::make('configuration.layers.1.styles.default.font_family')->label('Approved font')->options(array_combine(array_keys(DesignFontRegistry::FONTS), array_keys(DesignFontRegistry::FONTS))),
            ColorPicker::make('configuration.layers.1.colour')->label('Text colour'), KeyValue::make('configuration.layers.1.colours_by_variant')->label('Variant text colours'),
            Repeater::make('configuration.layers.1.items')->schema([TextInput::make('x')->numeric()->minValue(0)->maxValue(1), TextInput::make('y')->numeric()->minValue(0)->maxValue(1), TextInput::make('size')->numeric()->minValue(.001)->maxValue(1), Select::make('rotation')->options([0 => '0°', 90 => '90°'])])->columns(4)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('version')->label('Version'), TextColumn::make('status')->badge(), TextColumn::make('last_test_render_status')->label('Test render')->badge(), TextColumn::make('published_at')->dateTime()])->headerActions([CreateAction::make()->mutateDataUsing(function (array $data) {
            $data['created_by'] = auth()->id();

            return $data;
        })])->recordActions([
            Action::make('newDraft')->label('New draft')->visible(fn ($record) => $record->status === DesignTemplateVersionStatus::Published)->action(function ($record) {
                $draft = $record->replicate(['status', 'published_at', 'last_test_render_at', 'last_test_render_status', 'last_test_render_error', 'test_render_disk', 'test_render_storage_key']);
                $draft->version = $record->template->versions()->max('version') + 1;
                $draft->status = DesignTemplateVersionStatus::Draft;
                $draft->created_by = auth()->id();
                $draft->save();
                Notification::make()->title('Draft version created')->success()->send();
            }),
            Action::make('testRender')->visible(fn ($record) => $record->status === DesignTemplateVersionStatus::Draft)->schema([Select::make('variant_id')->options(ProductVariant::with('product')->get()->mapWithKeys(fn ($v) => [$v->id => $v->product->name.' — '.$v->name]))->required(), TextInput::make('name')->default('Sophie')->required(), FileUpload::make('character')->disk('local')->directory('admin/template-test-inputs')->image()->maxSize(10240)->required()])->action(function ($record, array $data) {
                app(TestRenderDesignTemplateVersion::class)->handle($record, ProductVariant::findOrFail($data['variant_id']), 'local', $data['character'], ['name' => $data['name']]);
                Notification::make()->title('Test render succeeded')->success()->send();
            }),
            Action::make('publish')->color('success')->visible(fn ($record) => $record->status === DesignTemplateVersionStatus::Draft)->action(function ($record) {
                try {
                    app(PublishDesignTemplateVersion::class)->handle($record);
                    Notification::make()->title('Template published')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Cannot publish')->body($e->getMessage())->danger()->send();
                }
            }),
            EditAction::make()->visible(fn ($record) => $record->status !== DesignTemplateVersionStatus::Published),
        ]);
    }
}
