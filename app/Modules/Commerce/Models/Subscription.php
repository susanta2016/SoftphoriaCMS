<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionDisplayStatus;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Application-owned Pro Membership state, one row per user — see
 * database/migrations/2026_08_23_090005_create_subscriptions_table.php.
 *
 * Why membership access is not per-track/per-release Entitlement rows: an
 * active subscriber can download the *entire* eligible catalogue, including
 * releases published after they subscribed. Materializing that as
 * Entitlement rows would mean either backfilling one per user per release
 * (unbounded, and wrong the instant a new Album ships) or a background sync
 * job — real complexity for no benefit. Access is instead checked live,
 * at request time, against isActive() below — see
 * App\Modules\Commerce\Actions\Entitlement\ResolveTrackAccessAction. This is
 * also why cancelling takes effect for *future* downloads immediately (no
 * stale rows to revoke) while "access continues until period end" falls out
 * for free — Stripe keeps status='active' through that grace window.
 */
#[Fillable([
    'user_id', 'stripe_customer_id', 'stripe_subscription_id', 'status', 'price_at_subscription',
    'currency', 'started_at', 'current_period_start', 'current_period_end', 'cancel_at_period_end',
    'cancelled_at', 'ended_at',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price_at_subscription' => 'decimal:2',
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * The one rule the whole subscription-access model rests on. Trusting
     * `status === active` directly is correct per Stripe's own semantics
     * (see class docblock); the current_period_end check is a
     * belt-and-suspenders guard against a delayed/missed webhook.
     *
     * Deliberately does not look at `cancel_at_period_end` at all — per the
     * client-confirmed rule, a Pro Member who cancels keeps full access
     * until the already-paid period actually ends. Stripe itself models
     * this identically: `status` stays `active` through that whole window
     * regardless of `cancel_at_period_end`, which is exactly why checking
     * `status` (plus the period-end backstop) is already correct and needs
     * no separate "is it cancelling" branch.
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && ($this->current_period_end === null || ! $this->current_period_end->isPast());
    }

    public function latestTransaction(): ?PaymentTransaction
    {
        return $this->transactions()->latest('occurred_at')->first();
    }

    /**
     * The four states Admin needs to tell apart at a glance — see
     * SubscriptionDisplayStatus's docblock for why this is computed, never
     * a stored column.
     */
    public function displayStatus(): SubscriptionDisplayStatus
    {
        if ($this->isActive()) {
            return $this->cancel_at_period_end
                ? SubscriptionDisplayStatus::CancelingAtPeriodEnd
                : SubscriptionDisplayStatus::Active;
        }

        if (in_array($this->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Unpaid, SubscriptionStatus::Incomplete], true)) {
            return SubscriptionDisplayStatus::PaymentProblem;
        }

        return SubscriptionDisplayStatus::Expired;
    }
}
