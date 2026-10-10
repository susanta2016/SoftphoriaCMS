<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Actions\AuditLog\DeleteAuditLogsAction;
use App\Actions\AuditLog\ExportAuditLogsAction;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                Action::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete audit log entry')
                    ->modalDescription('This entry will be permanently removed. A new entry recording the deletion is kept.')
                    ->action(function (AuditLog $record): void {
                        app(DeleteAuditLogsAction::class)->handle(AuditLog::query()->whereKey($record->getKey()), Auth::user());

                        Notification::make()->title('Audit log entry deleted')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportSelected')
                        ->label('Export selected (CSV)')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->fetchSelectedRecords(false)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Builder $selectedRecordsQuery): StreamedResponse => app(ExportAuditLogsAction::class)->handle($selectedRecordsQuery)),
                    BulkAction::make('deleteSelected')
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected audit log entries')
                        ->modalDescription('The selected entries will be permanently removed. Consider exporting them first. A new entry recording the deletion is kept.')
                        ->modalSubmitActionLabel('Delete permanently')
                        ->fetchSelectedRecords(false)
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Builder $selectedRecordsQuery): void {
                            $deleted = app(DeleteAuditLogsAction::class)->handle($selectedRecordsQuery, Auth::user());

                            Notification::make()
                                ->title($deleted === 1 ? '1 audit log entry deleted' : "{$deleted} audit log entries deleted")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No audit activity yet')
            ->emptyStateDescription('Actions performed in the admin panel will appear here.');
    }
}
