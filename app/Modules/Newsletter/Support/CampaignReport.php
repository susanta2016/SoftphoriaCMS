<?php

namespace App\Modules\Newsletter\Support;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Illuminate\Support\Facades\DB;

/**
 * A campaign's delivery metrics in one aggregate query over its recipient
 * rows (indexed by campaign + status). Keeps the three ideas the spec
 * requires apart: submitted (the provider accepted it), delivered (the
 * provider reported delivery — only with SES events configured) and
 * failed. Opens are approximate (privacy features inflate and hide them);
 * a click also counts as an open.
 */
final class CampaignReport
{
    /**
     * @param  array<string, int>  $metrics
     */
    private function __construct(public readonly array $metrics) {}

    public static function for(NewsletterCampaign $campaign): self
    {
        $submitted = "'".implode("','", CampaignRecipientStatus::submittedValues())."'";

        $row = DB::table('newsletter_campaign_recipients')
            ->where('newsletter_campaign_id', $campaign->id)
            ->selectRaw(implode(', ', [
                'count(*) as total',
                "sum(case when status in ({$submitted}) then 1 else 0 end) as submitted",
                'sum(case when delivered_at is not null then 1 else 0 end) as delivered',
                'sum(case when bounced_at is not null then 1 else 0 end) as bounced',
                "sum(case when status = 'failed' then 1 else 0 end) as failed",
                'sum(case when complained_at is not null then 1 else 0 end) as complained',
                "sum(case when status = 'skipped' then 1 else 0 end) as skipped",
                "sum(case when status = 'cancelled' then 1 else 0 end) as cancelled",
                "sum(case when status in ('pending', 'sending') then 1 else 0 end) as outstanding",
                'sum(case when opened_at is not null then 1 else 0 end) as opened',
                'sum(case when clicked_at is not null then 1 else 0 end) as clicked',
                'sum(case when unsubscribed_at is not null then 1 else 0 end) as unsubscribed',
            ]))
            ->first();

        return new self(array_map(fn ($value): int => (int) $value, (array) $row));
    }

    public function get(string $metric): int
    {
        return $this->metrics[$metric] ?? 0;
    }

    /**
     * $metric as a percentage of submitted emails, or null with nothing sent.
     */
    public function rate(string $metric): ?float
    {
        $submitted = $this->get('submitted');

        return $submitted > 0 ? round($this->get($metric) / $submitted * 100, 1) : null;
    }
}
