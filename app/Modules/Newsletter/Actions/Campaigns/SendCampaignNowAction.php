<?php

namespace App\Modules\Newsletter\Actions\Campaigns;

use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Jobs\StartCampaignJob;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draft (or Scheduled) -> Queued, then StartCampaignJob — the HTTP request
 * only snapshots and queues; every email is sent by the queue worker. The
 * row lock + status check make a double-click queue it once.
 */
class SendCampaignNowAction
{
    use EnsuresNewsletterAdmin;

    public function __construct(
        private readonly PrepareCampaignContent $prepare,
        private readonly AuditLogService $auditLog,
    ) {}

    public function handle(NewsletterCampaign $campaign, User $admin): NewsletterCampaign
    {
        $this->ensureAdmin($admin);

        $chain = (string) Str::uuid();

        $campaign = DB::transaction(function () use ($campaign, $admin, $chain): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());

            if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true)) {
                throw NewsletterException::invalidTransition($campaign->status, CampaignStatus::Queued);
            }

            $this->prepare->snapshot($campaign);

            $campaign->transitionTo(CampaignStatus::Queued, [
                'scheduled_at' => now(),
                'queued_at' => now(),
                'send_chain' => $chain,
                'updated_by' => $admin->id,
            ]);

            $this->auditLog->record($admin, 'newsletter_campaign.sent_now', $campaign);

            return $campaign;
        });

        StartCampaignJob::dispatch($campaign->id, $chain);

        return $campaign->refresh();
    }
}
