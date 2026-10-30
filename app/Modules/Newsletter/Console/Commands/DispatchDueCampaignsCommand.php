<?php

namespace App\Modules\Newsletter\Console\Commands;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Services\CampaignLifecycle;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Illuminate\Console\Command;

/**
 * Every minute (NewsletterServiceProvider): queues Scheduled campaigns
 * whose time has come, and restarts stalled ones. Does nothing while the
 * module is off — scheduled campaigns simply wait, and are revalidated
 * (CampaignLifecycle::revalidate()) when it is turned back on, so one that
 * missed its window by more than config('newsletter.stale_after_hours') is
 * marked Failed rather than sent late.
 */
class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'newsletter:dispatch-due';

    protected $description = 'Queue due scheduled newsletter campaigns and restart stalled ones';

    public function handle(CampaignLifecycle $lifecycle): int
    {
        if (! NewsletterFeature::enabled()) {
            $this->components->info('Newsletter Marketing is turned off — nothing dispatched.');

            return self::SUCCESS;
        }

        $queued = 0;

        NewsletterCampaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (NewsletterCampaign $campaign) use ($lifecycle, &$queued): void {
                if ($lifecycle->queueDue($campaign)) {
                    $queued++;
                }
            });

        $recovered = $lifecycle->recoverStalled();

        $this->components->info("Queued {$queued} campaign(s); recovered {$recovered} stalled campaign(s).");

        return self::SUCCESS;
    }
}
