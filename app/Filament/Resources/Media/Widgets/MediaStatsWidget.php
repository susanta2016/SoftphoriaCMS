<?php

namespace App\Filament\Resources\Media\Widgets;

use App\Models\Media;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header stat cards for the Media list (ADMIN-005), matching the
 * UserStatsWidget pattern (ARCHITECTURE.md §13) — scoped to this resource
 * rather than the ADMIN-002 operational dashboard.
 */
class MediaStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Files', Media::query()->count())
                ->icon(Heroicon::OutlinedRectangleStack),

            Stat::make('Images', Media::query()->where('mime_type', 'like', 'image/%')->count())
                ->icon(Heroicon::OutlinedPhoto)
                ->color('success'),

            Stat::make('Audio', Media::query()->where('mime_type', 'like', 'audio/%')->count())
                ->icon(Heroicon::OutlinedMusicalNote)
                ->color('warning'),

            Stat::make('Video', Media::query()->where('mime_type', 'like', 'video/%')->count())
                ->icon(Heroicon::OutlinedVideoCamera)
                ->color('info'),
        ];
    }
}
