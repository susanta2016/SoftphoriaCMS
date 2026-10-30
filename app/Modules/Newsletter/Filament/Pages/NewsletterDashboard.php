<?php

namespace App\Modules\Newsletter\Filament\Pages;

use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Filament\Widgets\NewsletterStatsWidget;
use App\Modules\Newsletter\Filament\Widgets\RecentCampaignsWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Newsletter Marketing > Dashboard — subscriber and campaign counts plus
 * recent activity.
 */
class NewsletterDashboard extends Page
{
    use GatedByNewsletterFeature;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'newsletter';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Newsletter Marketing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCampaign')
                ->label('New campaign')
                ->icon(Heroicon::OutlinedPlus)
                ->url(NewsletterCampaignResource::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NewsletterStatsWidget::class,
            RecentCampaignsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
