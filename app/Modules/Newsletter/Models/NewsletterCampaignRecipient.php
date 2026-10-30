<?php

namespace App\Modules\Newsletter\Models;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One address in a campaign's recipient snapshot, and its delivery state.
 * Rows are created in bulk by BuildCampaignRecipients and only change state
 * through NewsletterDeliveryService (sending) and RecordNewsletterEventAction
 * (what the provider reported afterwards).
 */
#[Fillable([
    'newsletter_campaign_id', 'newsletter_subscriber_id', 'email', 'first_name', 'last_name', 'token',
    'status', 'status_reason', 'attempts', 'claimed_at', 'message_id', 'provider_message_id',
    'sent_at', 'delivered_at', 'bounced_at', 'complained_at', 'failed_at',
    'opened_at', 'open_count', 'clicked_at', 'click_count', 'unsubscribed_at',
])]
class NewsletterCampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'failed_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NewsletterCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }

    /**
     * @return HasMany<NewsletterDeliveryLog, $this>
     */
    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NewsletterDeliveryLog::class, 'newsletter_campaign_recipient_id');
    }
}
