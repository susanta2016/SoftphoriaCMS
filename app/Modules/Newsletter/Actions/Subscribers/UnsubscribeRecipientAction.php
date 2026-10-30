<?php

namespace App\Modules\Newsletter\Actions\Subscribers;

use App\Modules\Newsletter\Actions\RecordNewsletterEventAction;
use App\Modules\Newsletter\Enums\NewsletterEventType;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Support\SuppressionList;

/**
 * A recipient unsubscribing from a campaign email — the page's button or
 * the mail client's one-click (RFC 8058) POST. Identified only by the
 * recipient's random token; no admin involved, effective immediately for
 * every future send (the global suppression list), and idempotent.
 *
 * Deliberately works even with the module switched off: someone's right to
 * unsubscribe from email they already received can't depend on it.
 */
class UnsubscribeRecipientAction
{
    public function __construct(
        private readonly SuppressionList $suppressions,
        private readonly RecordNewsletterEventAction $record,
    ) {}

    public function handle(NewsletterCampaignRecipient $recipient, string $via): void
    {
        $this->suppressions->suppress(
            $recipient->email,
            SuppressionReason::Unsubscribed,
            "Unsubscribed from campaign #{$recipient->newsletter_campaign_id} ({$via})",
        );

        $this->record->handle($recipient, NewsletterEventType::Unsubscribe, $via, 'unsubscribe|'.$recipient->id);
    }
}
