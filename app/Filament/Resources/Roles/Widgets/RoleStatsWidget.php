<?php

namespace App\Filament\Resources\Roles\Widgets;

use App\Models\Permission;
use App\Models\Role;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Roles list, scoped to this resource (§13) —
 * not the ADMIN-002 operational dashboard.
 */
class RoleStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Roles', Role::query()->count())
                ->icon(Heroicon::OutlinedShieldCheck),

            Stat::make('Total Permissions', Permission::query()->count())
                ->icon(Heroicon::OutlinedKey),

            Stat::make('Roles Without Permissions', Role::query()->doesntHave('permissions')->count())
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }
}
