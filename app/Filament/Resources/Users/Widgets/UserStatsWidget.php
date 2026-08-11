<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Users list, matching the reference UI
 * (Total Users / Active / Pending). Scoped to the Users resource rather
 * than app/Filament/Widgets so it only appears above the user list, not on
 * the ADMIN-002 operational dashboard.
 */
class UserStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::query()->count())
                ->icon(Heroicon::OutlinedUsers),

            Stat::make('Active', User::query()->where('status', UserStatus::Active->value)->count())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('Pending', User::query()->where('status', UserStatus::PendingVerification->value)->count())
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
        ];
    }
}
