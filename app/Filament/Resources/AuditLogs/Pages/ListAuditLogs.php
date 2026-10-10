<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Actions\AuditLog\ExportAuditLogsAction;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Widgets\AuditLogStatsWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * No CreateAction — audit log entries are created exclusively by
 * App\Shared\Services\AuditLogService, never hand-built in the admin
 * panel (see AuditLogResource's docblock). "Export" downloads every entry
 * matching the table's current search and filters as CSV.
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => app(ExportAuditLogsAction::class)->handle($this->getFilteredSortedTableQuery())),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AuditLogStatsWidget::class,
        ];
    }
}
