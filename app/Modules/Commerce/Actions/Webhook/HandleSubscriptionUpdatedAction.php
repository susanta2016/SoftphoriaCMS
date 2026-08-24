<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Support\Carbon;

/**
 * Handles Stripe's `customer.subscription.updated` — the event Stripe sends
 * for every status/period/cancellation change, including the moment
 * cancel_at_period_end flips true (status stays 'active' until the period
 * actually ends, so access naturally continues through that grace window —
 * see Subscription::isActive()) and the moment a renewal extends the period
 * (current_period_end moves forward, cancel_at_period_end/canceled_at reset
 * to false/null if the customer had previously cancelled and the
 * subscription rolled over — reflected here exactly as Stripe reports it,
 * never assumed).
 *
 * Every field below is read defensively (`?? existing value`, not `??
 * null`): a real Stripe subscription object always carries all of these,
 * but nothing here should be able to silently wipe out a previously
 * recorded value (e.g. a real cancellation's canceled_at) just because one
 * caller — a test, a future event type reusing this handler — sends a
 * payload missing a key.
 *
 * `current_period_start`/`current_period_end` moved off the top-level
 * Subscription object onto its first line item (`items.data[0].current_
 * period_*`) in recent Stripe API versions — this account's default among
 * them, confirmed empirically: a live `subscriptions->retrieve()` call
 * returns nothing at the top level, only nested under `items`. Reading
 * item-level first (falling back to the old top-level shape so existing
 * tests/fixtures built against it still work) is what actually keeps this
 * populated; reading top-level only left both columns permanently null,
 * which silently defeated the cancel-at-period-end grace window in
 * Subscription::isActive() (a null current_period_end reads as "still
 * active" forever, until the much-later `customer.subscription.deleted`
 * event finally cuts access — see HandleSubscriptionDeletedAction).
 */
class HandleSubscriptionUpdatedAction
{
    public function handle(StripeEvent $event): void
    {
        $data = $event->data;
        $subscription = Subscription::query()->where('stripe_subscription_id', $data['id'])->first();

        if ($subscription === null) {
            return;
        }

        $subscription->status = SubscriptionStatus::from($data['status']);
        $subscription->current_period_start = $this->periodTimestamp($data, 'current_period_start')
            ?? $subscription->current_period_start;
        $subscription->current_period_end = $this->periodTimestamp($data, 'current_period_end')
            ?? $subscription->current_period_end;
        $subscription->cancel_at_period_end = array_key_exists('cancel_at_period_end', $data)
            ? (bool) $data['cancel_at_period_end'] : $subscription->cancel_at_period_end;
        $subscription->cancelled_at = array_key_exists('canceled_at', $data)
            ? (filled($data['canceled_at']) ? Carbon::createFromTimestamp($data['canceled_at']) : null)
            : $subscription->cancelled_at;
        $subscription->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function periodTimestamp(array $data, string $field): ?Carbon
    {
        $timestamp = $data['items']['data'][0][$field] ?? $data[$field] ?? null;

        return $timestamp !== null ? Carbon::createFromTimestamp($timestamp) : null;
    }
}
