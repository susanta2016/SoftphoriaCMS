<?php

namespace App\Modules\Newsletter\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One send attempt to one campaign recipient. `response` is the provider's
 * reply or the (truncated) error — never shown outside the admin panel.
 */
#[Fillable(['newsletter_campaign_recipient_id', 'newsletter_campaign_id', 'attempt', 'status', 'mailer', 'provider_message_id', 'response'])]
class NewsletterDeliveryLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<NewsletterCampaignRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaignRecipient::class, 'newsletter_campaign_recipient_id');
    }
}
