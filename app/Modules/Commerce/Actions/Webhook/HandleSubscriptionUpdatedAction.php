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
        $subscription->current_period_start = isset($data['current_period_start'])
            ? Carbon::createFromTimestamp($data['current_period_start']) : $subscription->current_period_start;
        $subscription->current_period_end = isset($data['current_period_end'])
            ? Carbon::createFromTimestamp($data['current_period_end']) : $subscription->current_period_end;
        $subscription->cancel_at_period_end = array_key_exists('cancel_at_period_end', $data)
            ? (bool) $data['cancel_at_period_end'] : $subscription->cancel_at_period_end;
        $subscription->cancelled_at = array_key_exists('canceled_at', $data)
            ? (filled($data['canceled_at']) ? Carbon::createFromTimestamp($data['canceled_at']) : null)
            : $subscription->cancelled_at;
        $subscription->save();
    }
}
