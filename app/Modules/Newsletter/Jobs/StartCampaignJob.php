<?php

namespace App\Modules\Newsletter\Jobs;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Services\CampaignLifecycle;
use App\Modules\Newsletter\Support\NewsletterFeature;
use App\Modules\Newsletter\Support\RecipientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued -> Sending: takes the recipient snapshot (once — a retry finds it
 * already taken) and starts the SendCampaignBatchJob chain. Exits without
 * doing anything when the module is off or the campaign has moved on
 * (cancelled, or a newer chain took over); a campaign left Queued that way
 * is picked up again by CampaignLifecycle::recoverStalled().
 */
class StartCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $chain,
    ) {
        $this->onQueue(config('newsletter.queue'));
    }

    public function handle(RecipientResolver $resolver, CampaignLifecycle $lifecycle): void
    {
        if (! NewsletterFeature::enabled()) {
            return;
        }

        $campaign = NewsletterCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::Queued || $campaign->send_chain !== $this->chain) {
            return;
        }

        if (($problem = $lifecycle->revalidate($campaign)) !== null) {
            $campaign->transitionFromTo(CampaignStatus::Queued, CampaignStatus::Failed, ['status_reason' => $problem, 'completed_at' => now()]);

            return;
        }

        if ($campaign->recipients_snapshot_at === null) {
            $total = $resolver->snapshot($campaign);
            $campaign->forceFill(['recipients_total' => $total, 'recipients_snapshot_at' => now()])->save();
        }

        if (! $campaign->transitionFromTo(CampaignStatus::Queued, CampaignStatus::Sending, [
            'started_at' => now(),
            'last_progress_at' => now(),
        ])) {
            return;
        }

        if (! $lifecycle->finalizeIfDone($campaign)) {
            SendCampaignBatchJob::dispatch($campaign->id, $this->chain);
        }
    }
}
