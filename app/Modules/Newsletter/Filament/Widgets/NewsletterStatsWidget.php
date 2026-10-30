<?php

namespace App\Modules\Newsletter\Filament\Widgets;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Newsletter Marketing dashboard counts. Two grouped queries rather than
 * one count per tile. Only shown on NewsletterDashboard (the main admin
 * Dashboard filters this module's widgets out).
 */
class NewsletterStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return NewsletterFeature::enabled();
    }

    protected function getStats(): array
    {
        $subscribers = NewsletterSubscriber::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $campaigns = NewsletterCampaign::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $count = fn ($collection, string ...$keys): int => (int) collect($keys)->sum(fn (string $key) => $collection[$key] ?? 0);

        return [
            Stat::make('Total subscribers', number_format((int) $subscribers->sum()))->icon('heroicon-o-users'),
            Stat::make('Active', number_format($count($subscribers, SubscriberStatus::Subscribed->value)))->icon('heroicon-o-check-circle')->color('success'),
            Stat::make('Unsubscribed', number_format($count($subscribers, SubscriberStatus::Unsubscribed->value)))->icon('heroicon-o-no-symbol'),
            Stat::make('Suppressed / invalid', number_format($count($subscribers, SubscriberStatus::Bounced->value, SubscriberStatus::Complained->value, SubscriberStatus::Suppressed->value, SubscriberStatus::Invalid->value)))
                ->icon('heroicon-o-shield-exclamation')->color('danger'),
            Stat::make('Groups', number_format(NewsletterGroup::query()->count()))->icon('heroicon-o-rectangle-group'),
            Stat::make('Templates', number_format(NewsletterTemplate::query()->count()))->icon('heroicon-o-code-bracket-square'),
            Stat::make('Campaigns', number_format((int) $campaigns->sum()))->icon('heroicon-o-megaphone'),
            Stat::make('Drafts', number_format($count($campaigns, CampaignStatus::Draft->value)))->icon('heroicon-o-pencil-square'),
            Stat::make('Scheduled', number_format($count($campaigns, CampaignStatus::Scheduled->value, CampaignStatus::Queued->value, CampaignStatus::Sending->value)))
                ->description('Includes queued and sending')->icon('heroicon-o-clock')->color('info'),
            Stat::make('Sent', number_format($count($campaigns, CampaignStatus::Sent->value, CampaignStatus::PartiallySent->value)))->icon('heroicon-o-paper-airplane')->color('success'),
            Stat::make('Failed', number_format($count($campaigns, CampaignStatus::Failed->value)))->icon('heroicon-o-exclamation-triangle')->color('danger'),
        ];
    }
}
