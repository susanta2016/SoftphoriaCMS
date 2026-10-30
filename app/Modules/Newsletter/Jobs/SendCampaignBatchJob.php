<?php

namespace App\Modules\Newsletter\Jobs;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Services\CampaignLifecycle;
use App\Modules\Newsletter\Services\NewsletterDeliveryService;
use App\Modules\Newsletter\Support\NewsletterFeature;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Modules\Newsletter\Support\RenderedEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends the next batch of a campaign's pending recipients, at no more than
 * NewsletterSettings::ratePerSecond(), then queues itself for the next
 * batch — one chain per campaign (`send_chain`), so the rate limit holds
 * and the whole list is never loaded at once. Stops at once when the
 * module is turned off or the campaign is cancelled; a stopped chain is
 * restarted by CampaignLifecycle::recoverStalled().
 *
 * A recipient that failed with a provider error is retried by a later
 * batch, after a short backoff, until config('newsletter.max_attempts').
 */
class SendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    private const RETRY_BACKOFF_SECONDS = 60;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $chain,
    ) {
        $this->onQueue(config('newsletter.queue'));
    }

    public function handle(NewsletterDeliveryService $delivery, CampaignLifecycle $lifecycle, NewsletterSettings $settings): void
    {
        if (! NewsletterFeature::enabled() || ! $this->isCurrentChain()) {
            return;
        }

        $campaign = NewsletterCampaign::query()->findOrFail($this->campaignId);
        $snapshot = new RenderedEmail((string) $campaign->rendered_html, (string) $campaign->rendered_text, $campaign->tracked_links ?? []);

        $rate = $settings->ratePerSecond();
        // Keep each job well inside the worker timeout.
        $batchSize = max(1, min((int) config('newsletter.batch_size', 100), $rate * 60));
        $interval = 1_000_000 / $rate;

        $recipients = NewsletterCampaignRecipient::query()
            ->where('newsletter_campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->where(fn ($query) => $query->where('attempts', 0)->orWhere('claimed_at', '<=', now()->subSeconds(self::RETRY_BACKOFF_SECONDS)))
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        foreach ($recipients as $index => $recipient) {
            if ($index > 0 && $index % 10 === 0 && (! NewsletterFeature::enabled() || ! $this->isCurrentChain())) {
                return;
            }

            $started = hrtime(true);
            $delivery->deliver($campaign, $recipient, $snapshot);

            $elapsed = (hrtime(true) - $started) / 1000;

            if ($elapsed < $interval) {
                usleep((int) ($interval - $elapsed));
            }
        }

        $campaign->forceFill(['last_progress_at' => now()])->save();

        if (! $this->isCurrentChain() || $lifecycle->finalizeIfDone($campaign->refresh())) {
            return;
        }

        // Only recipients waiting out a retry backoff (or a worker's
        // in-flight send) remain: come back after the backoff. The sync
        // driver ignores delays and would spin, so there the scheduler's
        // stall recovery resumes the campaign instead.
        if ($recipients->isEmpty() && config('queue.connections.'.config('queue.default').'.driver') === 'sync') {
            return;
        }

        static::dispatch($campaign->id, $this->chain)
            ->delay($recipients->isEmpty() ? now()->addSeconds(self::RETRY_BACKOFF_SECONDS) : null);
    }

    private function isCurrentChain(): bool
    {
        return NewsletterCampaign::query()
            ->whereKey($this->campaignId)
            ->where('status', CampaignStatus::Sending->value)
            ->where('send_chain', $this->chain)
            ->exists();
    }
}
