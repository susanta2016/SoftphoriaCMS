<?php

namespace App\Modules\Newsletter\Actions\Campaigns;

use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A new Draft with the same settings, content and group selection — the
 * way to resend or reuse any campaign, including a Sent/Failed/Cancelled
 * one. Nothing about sending (snapshot, schedule, recipients) is copied.
 */
class DuplicateCampaignAction
{
    use EnsuresNewsletterAdmin;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(NewsletterCampaign $campaign, User $admin): NewsletterCampaign
    {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($campaign, $admin): NewsletterCampaign {
            $copy = NewsletterCampaign::query()->create([
                ...$campaign->only([
                    'subject', 'preheader', 'from_name', 'from_email', 'newsletter_template_id',
                    'content', 'cta_text', 'cta_url', 'timezone', 'track_opens', 'track_clicks',
                ]),
                'name' => Str::limit('Copy of '.$campaign->name, 150, ''),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $copy->syncGroupSelection(
                $campaign->includedGroups()->pluck('newsletter_groups.id')->all(),
                $campaign->excludedGroups()->pluck('newsletter_groups.id')->all(),
            );

            $this->auditLog->record($admin, 'newsletter_campaign.duplicated', $copy, ['source_campaign_id' => $campaign->id]);

            return $copy;
        });
    }
}
