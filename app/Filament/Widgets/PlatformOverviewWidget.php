<?php

namespace App\Filament\Widgets;

use App\Models\ContactRequest;
use App\Models\Page;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * ADMIN-002: registered-user and content counts read directly from the
 * existing DB-002/003 tables. Deliberately not an analytics widget — no
 * traffic, revenue, or trend metrics, per the Phase 1 exclusion in
 * Database_Specification_v1.0.docx and Core Specification Appendix A.3.
 */
class PlatformOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::query()->count())
                ->icon('heroicon-o-users'),

            Stat::make('Active User Accounts', User::query()->where('status', 'active')->count())
                ->icon('heroicon-o-user-circle')
                ->color('success'),

            Stat::make('Published Pages', Page::query()->where('status', 'published')->count())
                ->icon('heroicon-o-document-text'),

            Stat::make('New Contact Requests', ContactRequest::query()->where('status', 'new')->count())
                ->icon('heroicon-o-envelope')
                ->color('warning'),
        ];
    }
}
