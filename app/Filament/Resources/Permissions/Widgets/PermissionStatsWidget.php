<?php

namespace App\Filament\Resources\Permissions\Widgets;

use App\Models\Permission;
use App\Models\Role;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Permissions list, scoped to this resource
 * (§13) — not the ADMIN-002 operational dashboard.
 */
class PermissionStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Permissions', Permission::query()->count())
                ->icon(Heroicon::OutlinedKey),

            Stat::make('Total Roles', Role::query()->count())
                ->icon(Heroicon::OutlinedShieldCheck),

            Stat::make('Unassigned Permissions', Permission::query()->doesntHave('roles')->count())
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }
}
