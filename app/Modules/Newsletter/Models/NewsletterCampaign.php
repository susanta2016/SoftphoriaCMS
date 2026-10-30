<?php

namespace App\Modules\Newsletter\Models;

use App\Models\User;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A newsletter send. Editable only while Draft; from Scheduled on it sends
 * from its own rendered_html/rendered_text snapshot, never the live
 * template. Status only changes through transitionTo() /
 * transitionFromTo(), which enforce CampaignStatus::allowedTransitions().
 */
#[Fillable([
    'name', 'subject', 'preheader', 'from_name', 'from_email', 'newsletter_template_id',
    'content', 'cta_text', 'cta_url', 'status', 'timezone', 'scheduled_at',
    'rendered_html', 'rendered_text', 'tracked_links', 'track_opens', 'track_clicks',
    'groups_snapshot', 'content_snapshot_at', 'recipients_snapshot_at', 'recipients_total', 'send_chain',
    'queued_at', 'started_at', 'last_progress_at', 'completed_at', 'cancelled_at', 'status_reason',
    'created_by', 'updated_by',
])]
class NewsletterCampaign extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'timezone' => 'Asia/Kolkata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'tracked_links' => 'array',
            'track_opens' => 'boolean',
            'track_clicks' => 'boolean',
            'groups_snapshot' => 'array',
            'content_snapshot_at' => 'datetime',
            'recipients_snapshot_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'last_progress_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NewsletterTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NewsletterTemplate::class, 'newsletter_template_id');
    }

    /**
     * Every selected group, included or excluded (pivot `excluded`).
     *
     * @return BelongsToMany<NewsletterGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterGroup::class, 'newsletter_campaign_group')
            ->withPivot('excluded');
    }

    /**
     * @return BelongsToMany<NewsletterGroup, $this>
     */
    public function includedGroups(): BelongsToMany
    {
        return $this->groups()->wherePivot('excluded', false);
    }

    /**
     * @return BelongsToMany<NewsletterGroup, $this>
     */
    public function excludedGroups(): BelongsToMany
    {
        return $this->groups()->wherePivot('excluded', true);
    }

    /**
     * Replaces the include/exclude selection. A group in both lists is
     * treated as excluded — exclusion always wins.
     *
     * @param  array<int, int|string>  $includedIds
     * @param  array<int, int|string>  $excludedIds
     */
    public function syncGroupSelection(array $includedIds, array $excludedIds): void
    {
        $excludedIds = array_map('intval', $excludedIds);
        $sync = [];

        foreach (array_map('intval', $includedIds) as $id) {
            $sync[$id] = ['excluded' => in_array($id, $excludedIds, true)];
        }

        foreach ($excludedIds as $id) {
            $sync[$id] = ['excluded' => true];
        }

        $this->groups()->sync($sync);
    }

    /**
     * @return HasMany<NewsletterCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Changes the status in memory and saves, refusing a transition
     * CampaignStatus doesn't allow.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws NewsletterException
     */
    public function transitionTo(CampaignStatus $next, array $attributes = []): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw NewsletterException::invalidTransition($this->status, $next);
        }

        $this->forceFill(['status' => $next, ...$attributes])->save();
    }

    /**
     * Atomic compare-and-set: moves the row from $from to $to only if it is
     * still $from in the database. Returns false when another process got
     * there first (the dispatcher's duplicate-run protection) — no
     * exception, the caller just does nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionFromTo(CampaignStatus $from, CampaignStatus $to, array $attributes = []): bool
    {
        if (! $from->canTransitionTo($to)) {
            throw NewsletterException::invalidTransition($from, $to);
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('status', $from->value)
            ->update(['status' => $to->value, 'updated_at' => now(), ...$this->serializeForUpdate($attributes)]);

        if ($updated === 1) {
            $this->refresh();

            return true;
        }

        return false;
    }

    /**
     * The schedule shown back in the campaign's own timezone.
     */
    public function scheduledAtLocal(): ?Carbon
    {
        return $this->scheduled_at?->copy()->setTimezone($this->timezone ?: config('newsletter.default_timezone'));
    }

    /**
     * Per-status recipient counts — one grouped query.
     *
     * @return array<string, int>
     */
    public function recipientStatusCounts(): array
    {
        return $this->recipients()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function hasOutstandingRecipients(): bool
    {
        return $this->recipients()
            ->whereIn('status', [CampaignRecipientStatus::Pending->value, CampaignRecipientStatus::Sending->value])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function serializeForUpdate(array $attributes): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $attributes);
    }
}
