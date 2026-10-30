<?php

namespace App\Models;

use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Modules\Newsletter\Models\NewsletterSuppression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'email', 'name', 'first_name', 'last_name', 'status', 'source', 'profile_fields',
    'consented_at', 'unsubscribed_at',
    'confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at',
    'ses_event_at', 'ses_event_type', 'ses_event_reason',
])]
class NewsletterSubscriber extends Model
{
    /**
     * Statuses that must never receive a newsletter send — see scopeSendable().
     * suppressed/invalid are set by the Newsletter Marketing module
     * (App\Modules\Newsletter\Support\SuppressionList).
     */
    public const SUPPRESSED_STATUSES = ['bounced', 'complained', 'suppressed', 'invalid'];

    protected function casts(): array
    {
        return [
            'profile_fields' => 'array',
            'consented_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'confirmation_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'ses_event_at' => 'datetime',
        ];
    }

    /**
     * The one normalization point for a newsletter email address — used for
     * both the public signup form and SES recipient matching
     * (App\Actions\Newsletter\Webhook), so the two can never disagree on
     * what counts as "the same" address.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * The only status eligible for a newsletter send. Any future
     * newsletter-sending feature must query through this scope rather than
     * filtering status inline, so pending/unsubscribed/bounced/complained
     * subscribers can never be added to a send by an inline query that
     * forgets one of them. Also excludes every address on the global
     * suppression list (newsletter_suppressions), whatever the row's own
     * status says — a stale `subscribed` row can't outrank a suppression.
     */
    public function scopeSendable(Builder $query): Builder
    {
        return $query
            ->where('status', 'subscribed')
            ->whereNotExists(function ($suppressed): void {
                $suppressed->selectRaw('1')
                    ->from((new NewsletterSuppression)->getTable())
                    ->whereColumn('newsletter_suppressions.email', 'newsletter_subscribers.email');
            });
    }

    /**
     * Newsletter Marketing groups. Membership never affects whether the
     * subscriber is sendable — see scopeSendable().
     *
     * @return BelongsToMany<NewsletterGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterGroup::class, 'newsletter_group_subscriber')
            ->withPivot('created_at');
    }

    public function fullName(): string
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : (string) $this->name;
    }
}
