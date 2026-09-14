<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email', 'name', 'status', 'consented_at', 'unsubscribed_at',
    'confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at',
    'ses_event_at', 'ses_event_type', 'ses_event_reason',
])]
class NewsletterSubscriber extends Model
{
    /**
     * Statuses that must never receive a newsletter send — see scopeSendable().
     */
    public const SUPPRESSED_STATUSES = ['bounced', 'complained'];

    protected function casts(): array
    {
        return [
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
     * forgets one of them.
     */
    public function scopeSendable(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }
}
