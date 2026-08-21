<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;

/**
 * Handles Stripe's `customer.subscription.deleted` — the subscription has
 * fully ended (not merely set to cancel at period end, which is
 * `customer.subscription.updated` with cancel_at_period_end=true instead).
 * From this point Subscription::isActive() returns false immediately.
 */
class HandleSubscriptionDeletedAction
{
    public function handle(StripeEvent $event): void
    {
        $subscription = Subscription::query()->where('stripe_subscription_id', $event->data['id'])->first();

        if ($subscription === null) {
            return;
        }

        $subscription->status = SubscriptionStatus::Canceled;
        $subscription->ended_at = now();
        $subscription->save();
    }
}
