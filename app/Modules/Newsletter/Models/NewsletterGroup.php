<?php

namespace App\Modules\Newsletter\Models;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A subscriber list. Deleting one only removes its memberships
 * (newsletter_group_subscriber cascades), never subscribers — and is
 * refused while a scheduled/queued/sending campaign still targets it
 * (isInUseByActiveCampaign()).
 */
#[Fillable(['name', 'description'])]
class NewsletterGroup extends Model
{
    /**
     * @return BelongsToMany<NewsletterSubscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterSubscriber::class, 'newsletter_group_subscriber')
            ->withPivot('created_at');
    }

    /**
     * @return BelongsToMany<NewsletterCampaign, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterCampaign::class, 'newsletter_campaign_group')
            ->withPivot('excluded');
    }

    public function isInUseByActiveCampaign(): bool
    {
        return $this->campaigns()
            ->whereIn('status', array_map(fn (CampaignStatus $status): string => $status->value, CampaignStatus::active()))
            ->exists();
    }
}
