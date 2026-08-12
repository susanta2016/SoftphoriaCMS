<?php

namespace App\Filament\Resources\Pages\Widgets;

use App\Enums\PageStatus;
use App\Models\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PageStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Draft', Page::query()->where('status', PageStatus::Draft)->count())
                ->icon(Heroicon::OutlinedPencil),

            Stat::make('Scheduled', Page::query()->where('status', PageStatus::Scheduled)->count())
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('Published', Page::query()->where('status', PageStatus::Published)->count())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('Archived', Page::query()->where('status', PageStatus::Archived)->count())
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('danger'),
        ];
    }
}
