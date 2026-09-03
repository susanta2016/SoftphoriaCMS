<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Widgets\AuditLogStatsWidget;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction — audit log entries are created exclusively by
 * App\Shared\Services\AuditLogService, never hand-built in the admin
 * panel (see AuditLogResource's docblock).
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AuditLogStatsWidget::class,
        ];
    }
}
