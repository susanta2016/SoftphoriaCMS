<?php

namespace App\Modules\Newsletter\Services;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Jobs\SendCampaignBatchJob;
use App\Modules\Newsletter\Jobs\StartCampaignJob;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\SenderIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The background side of a campaign: queueing a due one, finishing one
 * that has no recipients left, and recovering one whose jobs were lost
 * (module turned off mid-send, worker restarted). Used by the scheduler
 * command and the jobs; never sends email itself.
 */
class CampaignLifecycle
{
    public function __construct(private readonly SenderIdentity $sender) {}

    /**
     * Scheduled -> Queued for a due campaign, then StartCampaignJob. The
     * compare-and-set transition is the duplicate-run guard: of two
     * overlapping scheduler runs only one moves the row, and only that one
     * dispatches.
     */
    public function queueDue(NewsletterCampaign $campaign): bool
    {
        if (($problem = $this->revalidate($campaign)) !== null) {
            $campaign->transitionFromTo(CampaignStatus::Scheduled, CampaignStatus::Failed, [
                'status_reason' => $problem,
                'completed_at' => now(),
            ]);

            return false;
        }

        $chain = (string) Str::uuid();

        if (! $campaign->transitionFromTo(CampaignStatus::Scheduled, CampaignStatus::Queued, [
            'queued_at' => now(),
            'send_chain' => $chain,
        ])) {
            return false;
        }

        StartCampaignJob::dispatch($campaign->id, $chain);

        return true;
    }

    /**
     * Why a Scheduled/Queued campaign may no longer go out, or null. Run
     * whenever one is picked up — including after the module was off.
     */
    public function revalidate(NewsletterCampaign $campaign): ?string
    {
        $staleHours = (int) config('newsletter.stale_after_hours', 24);
        $due = $campaign->scheduled_at ?? $campaign->queued_at;

        if ($due !== null && $due->lt(now()->subHours($staleHours))) {
            return "Not sent: picked up more than {$staleHours} hours after its scheduled time (the module or scheduler was off). Duplicate the campaign to send it.";
        }

        if (blank($campaign->rendered_html) || $campaign->content_snapshot_at === null) {
            return 'Not sent: the campaign has no content snapshot.';
        }

        if (($senderProblem = $this->sender->validate($campaign->from_email)) !== null) {
            return 'Not sent: '.$senderProblem;
        }

        return null;
    }

    /**
     * Sending -> Sent / Partially Sent / Failed once no recipient is left
     * pending or mid-send. Skipped (no longer eligible) and Cancelled
     * recipients don't count as failures.
     */
    public function finalizeIfDone(NewsletterCampaign $campaign): bool
    {
        if ($campaign->hasOutstandingRecipients()) {
            return false;
        }

        $counts = $campaign->recipientStatusCounts();
        $submitted = array_sum(array_intersect_key($counts, array_flip(CampaignRecipientStatus::submittedValues())));
        $failed = $counts[CampaignRecipientStatus::Failed->value] ?? 0;

        [$status, $reason] = match (true) {
            $failed === 0 => [CampaignStatus::Sent, $submitted === 0 ? 'No recipient was still eligible at send time.' : null],
            $submitted > 0 => [CampaignStatus::PartiallySent, "{$failed} recipient(s) failed."],
            default => [CampaignStatus::Failed, 'Every send attempt failed — check the mail settings and delivery log.'],
        };

        return $campaign->transitionFromTo(CampaignStatus::Sending, $status, [
            'completed_at' => now(),
            'status_reason' => $reason,
        ]);
    }

    /**
     * Restarts campaigns whose job chain stopped: Queued but never started,
     * or Sending with no progress for config('newsletter.stalled_after_minutes').
     * Revalidated first, like a due scheduled campaign. Recipients stuck at
     * Sending by a dead worker are marked Failed, never re-sent.
     */
    public function recoverStalled(): int
    {
        $cutoff = now()->subMinutes((int) config('newsletter.stalled_after_minutes', 15));
        $recovered = 0;

        $stalled = NewsletterCampaign::query()
            ->where(fn ($query) => $query
                ->where(fn ($queued) => $queued->where('status', CampaignStatus::Queued->value)->where('queued_at', '<', $cutoff))
                ->orWhere(fn ($sending) => $sending->where('status', CampaignStatus::Sending->value)->where('last_progress_at', '<', $cutoff)))
            ->get();

        foreach ($stalled as $campaign) {
            $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Sending->value)
                ->where('claimed_at', '<', $cutoff)
                ->update([
                    'status' => CampaignRecipientStatus::Failed->value,
                    'status_reason' => 'Send outcome unknown (worker stopped mid-send); not retried to avoid a duplicate.',
                    'failed_at' => now(),
                    'updated_at' => now(),
                ]);

            $chain = (string) Str::uuid();

            if ($campaign->status === CampaignStatus::Queued) {
                if (($problem = $this->revalidate($campaign)) !== null) {
                    $campaign->transitionFromTo(CampaignStatus::Queued, CampaignStatus::Failed, ['status_reason' => $problem, 'completed_at' => now()]);

                    continue;
                }

                $campaign->forceFill(['send_chain' => $chain, 'queued_at' => now()])->save();
                StartCampaignJob::dispatch($campaign->id, $chain);
            } else {
                $campaign->forceFill(['send_chain' => $chain, 'last_progress_at' => now()])->save();

                if (! $this->finalizeIfDone($campaign)) {
                    SendCampaignBatchJob::dispatch($campaign->id, $chain);
                }
            }

            Log::info('Newsletter campaign recovered after stalling', ['campaign_id' => $campaign->id]);
            $recovered++;
        }

        return $recovered;
    }
}
