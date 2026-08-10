<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Operational queue health from the existing jobs/failed_jobs tables
 * (CORE-001 defaults). This is application/system status, not a business
 * analytics metric — no traffic, geography, device, or revenue data.
 */
class SystemStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            Stat::make('Queued Jobs', DB::table('jobs')->count())
                ->icon('heroicon-o-queue-list'),

            Stat::make('Failed Jobs', $failedJobs)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }
}
