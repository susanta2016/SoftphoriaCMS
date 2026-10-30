<?php

namespace App\Modules\Newsletter\Actions\Campaigns;

use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled/Queued/Sending -> Cancelled. Recipients not yet sent become
 * Cancelled; ones already sent stay in the report. A batch job mid-run
 * stops within a few recipients (it re-checks the campaign status).
 * Unschedule (back to Draft) is the separate unschedule() — for edits.
 */
class CancelCampaignAction
{
    use EnsuresNewsletterAdmin;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(NewsletterCampaign $campaign, User $admin): NewsletterCampaign
    {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($campaign, $admin): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());

            $campaign->transitionTo(CampaignStatus::Cancelled, ['cancelled_at' => now(), 'updated_by' => $admin->id]);

            $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Pending->value)
                ->update(['status' => CampaignRecipientStatus::Cancelled->value, 'status_reason' => 'Campaign cancelled.', 'updated_at' => now()]);

            $this->auditLog->record($admin, 'newsletter_campaign.cancelled', $campaign);

            return $campaign;
        });
    }

    /**
     * Scheduled -> Draft, clearing the snapshot so the next schedule takes a
     * fresh one from the (possibly edited) content.
     */
    public function unschedule(NewsletterCampaign $campaign, User $admin): NewsletterCampaign
    {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($campaign, $admin): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());

            if ($campaign->status !== CampaignStatus::Scheduled) {
                throw NewsletterException::invalidTransition($campaign->status, CampaignStatus::Draft);
            }

            $campaign->transitionTo(CampaignStatus::Draft, [
                'scheduled_at' => null,
                'rendered_html' => null,
                'rendered_text' => null,
                'tracked_links' => null,
                'content_snapshot_at' => null,
                'updated_by' => $admin->id,
            ]);

            $this->auditLog->record($admin, 'newsletter_campaign.unscheduled', $campaign);

            return $campaign;
        });
    }
}
