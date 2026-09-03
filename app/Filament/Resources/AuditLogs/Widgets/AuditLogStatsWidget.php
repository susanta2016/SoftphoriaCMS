<?php

namespace App\Filament\Resources\AuditLogs\Widgets;

use App\Models\AuditLog;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Audit Log list, scoped to this resource
 * (docs/ARCHITECTURE.md §13) — plain counts only, not the security-
 * analytics dashboard ADMIN-011 explicitly excludes.
 */
class AuditLogStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Entries', AuditLog::query()->count())
                ->icon(Heroicon::OutlinedClipboardDocumentList),

            Stat::make('Today', AuditLog::query()->whereDate('created_at', today())->count())
                ->icon(Heroicon::OutlinedClock),
        ];
    }
}
