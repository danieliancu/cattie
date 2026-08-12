<?php

namespace App\Filament\Resources\AdminAuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('admin.email')->label('Admin'), TextColumn::make('action')->badge()->searchable(), TextColumn::make('subject_type')->formatStateUsing(fn ($state) => class_basename($state)), TextColumn::make('subject_id')->toggleable(),
        ])->defaultSort('created_at', 'desc');
    }
}
