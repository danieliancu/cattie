<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Enums\DesignTemplateVersionStatus;
use App\Models\DesignTemplateVersion;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DesignTemplateAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'designTemplateAssignments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('design_template_version_id')->label('Published template version')->options(DesignTemplateVersion::with('template')->where('status', DesignTemplateVersionStatus::Published)->get()->mapWithKeys(fn ($v) => [$v->id => $v->template->name.' v'.$v->version]))->required(), Toggle::make('is_active')->default(true)]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('version.template.name')->label('Template'), TextColumn::make('version.version')->label('Version'), TextColumn::make('is_active')->badge()])->headerActions([CreateAction::make()->after(fn ($record) => app(RecordAdminAudit::class)->handle('template.assignment.changed', $record))])->recordActions([EditAction::make()->after(fn ($record) => app(RecordAdminAudit::class)->handle('template.assignment.changed', $record))]);
    }
}
