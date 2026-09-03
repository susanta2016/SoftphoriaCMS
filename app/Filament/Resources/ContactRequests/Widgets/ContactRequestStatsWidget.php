<?php

namespace App\Filament\Resources\ContactRequests\Widgets;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Contact Requests list, scoped to this
 * resource (§13) — not the ADMIN-002 operational dashboard.
 */
class ContactRequestStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total', ContactRequest::query()->count())
                ->icon(Heroicon::OutlinedEnvelope),

            Stat::make('New', ContactRequest::query()->where('status', ContactRequestStatus::New)->count())
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('Resolved', ContactRequest::query()->where('status', ContactRequestStatus::Resolved)->count())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
        ];
    }
}
