<?php

namespace App\Modules\Newsletter\Actions\Campaigns;

use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draft -> Scheduled. $localDateTime is read in $timezone (default
 * Asia/Kolkata) and stored as UTC. The content snapshot is taken now, so
 * template edits after this never change what goes out; the recipient
 * snapshot is taken when sending starts (StartCampaignJob), and consent is
 * re-checked for every recipient at send time.
 */
class ScheduleCampaignAction
{
    use EnsuresNewsletterAdmin;

    public function __construct(
        private readonly PrepareCampaignContent $prepare,
        private readonly AuditLogService $auditLog,
    ) {}

    public function handle(NewsletterCampaign $campaign, User $admin, string $localDateTime, string $timezone): NewsletterCampaign
    {
        $this->ensureAdmin($admin);

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw NewsletterException::invalid('Choose a valid timezone.');
        }

        $scheduledAt = Carbon::parse($localDateTime, $timezone)->utc();

        if ($scheduledAt->lte(now()->addMinute())) {
            throw NewsletterException::invalid('The scheduled time must be in the future.');
        }

        return DB::transaction(function () use ($campaign, $admin, $scheduledAt, $timezone): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());

            if ($campaign->status !== CampaignStatus::Draft) {
                throw NewsletterException::invalidTransition($campaign->status, CampaignStatus::Scheduled);
            }

            $this->prepare->snapshot($campaign);

            $campaign->transitionTo(CampaignStatus::Scheduled, [
                'scheduled_at' => $scheduledAt,
                'timezone' => $timezone,
                'updated_by' => $admin->id,
            ]);

            $this->auditLog->record($admin, 'newsletter_campaign.scheduled', $campaign, [
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'timezone' => $timezone,
            ]);

            return $campaign;
        });
    }
}
