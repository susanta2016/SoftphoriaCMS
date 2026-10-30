<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Enums\NewsletterEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened to a sent campaign email. Written only by
 * App\Modules\Newsletter\Actions\RecordNewsletterEventAction, which is what
 * makes duplicate webhook deliveries count once (unique dedupe_key).
 */
#[Fillable(['newsletter_campaign_id', 'newsletter_campaign_recipient_id', 'type', 'source', 'dedupe_key', 'detail', 'occurred_at'])]
class NewsletterEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => NewsletterEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NewsletterCampaignRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaignRecipient::class, 'newsletter_campaign_recipient_id');
    }
}
