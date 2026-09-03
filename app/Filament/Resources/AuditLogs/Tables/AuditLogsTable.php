<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextColumn::make('action')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity_type')
                    ->label('Entity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Actor')
                    ->relationship('user', 'name'),
                SelectFilter::make('action')
                    ->options(fn (): array => AuditLog::query()
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
                SelectFilter::make('entity_type')
                    ->label('Entity')
                    ->options(fn (): array => AuditLog::query()
                        ->distinct()
                        ->orderBy('entity_type')
                        ->pluck('entity_type', 'entity_type')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('View Details'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No audit activity yet')
            ->emptyStateDescription('Actions performed in the admin panel will appear here.');
    }
}
