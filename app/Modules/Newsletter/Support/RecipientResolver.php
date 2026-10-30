<?php

namespace App\Modules\Newsletter\Support;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * Who a campaign goes to: subscribers in ANY included group, minus anyone
 * in ANY excluded group, limited to NewsletterSubscriber::scopeSendable()
 * (Active, not on the suppression list). One row per subscriber however
 * many selected groups they're in, and subscriber emails are unique, so
 * one email per address — the snapshot's unique (campaign, email) index
 * backs that up.
 */
class RecipientResolver
{
    /**
     * @param  array<int, int|string>  $includedGroupIds
     * @param  array<int, int|string>  $excludedGroupIds
     * @return Builder<NewsletterSubscriber>
     */
    public function eligible(array $includedGroupIds, array $excludedGroupIds = []): Builder
    {
        $included = array_map('intval', $includedGroupIds);
        $excluded = array_map('intval', $excludedGroupIds);

        return NewsletterSubscriber::query()
            ->sendable()
            ->when($included === [], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereExists(fn (QueryBuilder $membership) => $this->membership($membership, $included))
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotExists(
                fn (QueryBuilder $membership) => $this->membership($membership, $excluded),
            ));
    }

    /**
     * @param  array<int, int|string>  $includedGroupIds
     * @param  array<int, int|string>  $excludedGroupIds
     */
    public function count(array $includedGroupIds, array $excludedGroupIds = []): int
    {
        return $this->eligible($includedGroupIds, $excludedGroupIds)->count();
    }

    public function countForCampaign(NewsletterCampaign $campaign): int
    {
        return $this->count(
            $campaign->includedGroups()->pluck('newsletter_groups.id')->all(),
            $campaign->excludedGroups()->pluck('newsletter_groups.id')->all(),
        );
    }

    /**
     * Writes the recipient snapshot in chunks — never the whole list in
     * memory. insertOrIgnore + the unique (campaign, email) index make a
     * re-run (a retried job) add nothing twice.
     */
    public function snapshot(NewsletterCampaign $campaign): int
    {
        $query = $this->eligible(
            $campaign->includedGroups()->pluck('newsletter_groups.id')->all(),
            $campaign->excludedGroups()->pluck('newsletter_groups.id')->all(),
        );

        $query->select(['id', 'email', 'first_name', 'last_name', 'name'])
            ->chunkById(1000, function ($subscribers) use ($campaign): void {
                $now = now();

                $rows = $subscribers->map(fn (NewsletterSubscriber $subscriber): array => [
                    'newsletter_campaign_id' => $campaign->id,
                    'newsletter_subscriber_id' => $subscriber->id,
                    'email' => NewsletterSubscriber::normalizeEmail($subscriber->email),
                    'first_name' => $subscriber->first_name ?? (Str::of((string) $subscriber->name)->before(' ')->value() ?: null),
                    'last_name' => $subscriber->last_name,
                    'token' => Str::random(48),
                    'status' => CampaignRecipientStatus::Pending->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                NewsletterCampaignRecipient::query()->insertOrIgnore($rows);
            });

        return $campaign->recipients()->count();
    }

    /**
     * @param  array<int, int>  $groupIds
     */
    private function membership(QueryBuilder $query, array $groupIds): void
    {
        $query->selectRaw('1')
            ->from('newsletter_group_subscriber')
            ->whereColumn('newsletter_group_subscriber.newsletter_subscriber_id', 'newsletter_subscribers.id')
            ->whereIn('newsletter_group_subscriber.newsletter_group_id', $groupIds === [] ? [0] : $groupIds);
    }
}
