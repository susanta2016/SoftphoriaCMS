<?php

namespace App\Modules\Newsletter\Actions;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\NewsletterEventType;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Stores one post-send event and applies it to the recipient — once. The
 * insert is insertOrIgnore on the unique dedupe_key, and the recipient is
 * only touched when the row was actually new, so an SNS redelivery or a
 * double click within the same minute changes nothing.
 *
 * Status only moves forward: a delivery never overwrites a bounce or
 * complaint that was recorded first (SES events can arrive out of order).
 */
class RecordNewsletterEventAction
{
    public function handle(
        NewsletterCampaignRecipient $recipient,
        NewsletterEventType $type,
        string $source,
        string $dedupeIdentity,
        ?Carbon $occurredAt = null,
        ?string $detail = null,
    ): bool {
        $occurredAt ??= now();

        $inserted = NewsletterEvent::query()->insertOrIgnore([
            'newsletter_campaign_id' => $recipient->newsletter_campaign_id,
            'newsletter_campaign_recipient_id' => $recipient->id,
            'type' => $type->value,
            'source' => $source,
            'dedupe_key' => hash('sha256', $dedupeIdentity),
            'detail' => $detail !== null ? Str::limit($detail, 2000, '') : null,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        if ($inserted !== 1) {
            return false;
        }

        $this->apply($recipient, $type, $occurredAt);

        return true;
    }

    private function apply(NewsletterCampaignRecipient $recipient, NewsletterEventType $type, Carbon $at): void
    {
        $recipient->refresh();
        $status = $recipient->status;
        $final = [CampaignRecipientStatus::Bounced, CampaignRecipientStatus::Complained];

        match ($type) {
            NewsletterEventType::Delivery => $recipient->forceFill([
                'delivered_at' => $recipient->delivered_at ?? $at,
                'status' => in_array($status, $final, true) ? $status : CampaignRecipientStatus::Delivered,
            ]),
            NewsletterEventType::Bounce => $recipient->forceFill([
                'bounced_at' => $recipient->bounced_at ?? $at,
                'status' => $status === CampaignRecipientStatus::Complained ? $status : CampaignRecipientStatus::Bounced,
            ]),
            NewsletterEventType::Complaint => $recipient->forceFill([
                'complained_at' => $recipient->complained_at ?? $at,
                'status' => CampaignRecipientStatus::Complained,
            ]),
            NewsletterEventType::Reject, NewsletterEventType::RenderingFailure => $recipient->forceFill([
                'failed_at' => $recipient->failed_at ?? $at,
                'status' => CampaignRecipientStatus::Failed,
                'status_reason' => $type->getLabel(),
            ]),
            NewsletterEventType::Open => $recipient->forceFill([
                'opened_at' => $recipient->opened_at ?? $at,
                'open_count' => $recipient->open_count + 1,
            ]),
            NewsletterEventType::Click => $recipient->forceFill([
                'clicked_at' => $recipient->clicked_at ?? $at,
                'click_count' => $recipient->click_count + 1,
                // A click proves an open even when the pixel was blocked.
                'opened_at' => $recipient->opened_at ?? $at,
            ]),
            NewsletterEventType::Unsubscribe => $recipient->forceFill([
                'unsubscribed_at' => $recipient->unsubscribed_at ?? $at,
            ]),
            NewsletterEventType::SoftBounce, NewsletterEventType::DeliveryDelay => null,
        };

        if ($recipient->isDirty()) {
            $recipient->save();
        }
    }
}
