<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Support\Facades\DB;

/**
 * Handles Stripe's `checkout.session.completed`, for both modes a future
 * checkout controller creates: `payment` (a Single/Album Order) and
 * `subscription` (Pro Member signup). Looks the Order up by
 * `stripe_checkout_session_id` (set when the session was created) — see
 * StripeGateway::createCheckoutSessionForOrder()'s `client_reference_id`/
 * metadata, which a future checkout controller stamps onto the Order before
 * redirecting.
 */
class HandleCheckoutSessionCompletedAction
{
    public function __construct(private readonly MarkOrderPaidAction $markOrderPaid) {}

    public function handle(StripeEvent $event): void
    {
        $session = $event->data;
        $mode = $session['mode'] ?? null;

        if ($mode === 'subscription') {
            $this->handleSubscriptionCheckout($event, $session);

            return;
        }

        $order = Order::query()->where('stripe_checkout_session_id', $session['id'])->first()
            ?? Order::query()->where('public_id', $session['client_reference_id'] ?? null)->first();

        if ($order === null) {
            return;
        }

        if ($order->stripe_checkout_session_id === null) {
            $order->stripe_checkout_session_id = $session['id'];
            $order->save();
        }

        $this->markOrderPaid->handle($order, (string) $session['payment_intent'], $event->id);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleSubscriptionCheckout(StripeEvent $event, array $session): void
    {
        $userId = $session['client_reference_id'] ?? $session['metadata']['user_id'] ?? null;

        if ($userId === null || PaymentTransaction::query()->where('provider_event_id', $event->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($session, $event, $userId): void {
            $subscription = Subscription::query()->firstOrNew(['user_id' => (int) $userId]);
            $subscription->stripe_customer_id = $session['customer'] ?? $subscription->stripe_customer_id;
            $subscription->stripe_subscription_id = $session['subscription'] ?? $subscription->stripe_subscription_id;
            $subscription->status = SubscriptionStatus::Active;
            $subscription->started_at ??= now();
            $subscription->save();

            $transaction = new PaymentTransaction;
            $transaction->subscription_id = $subscription->getKey();
            $transaction->type = PaymentTransactionType::SubscriptionInvoicePaid;
            $transaction->status = PaymentTransactionStatus::Succeeded;
            $transaction->provider_event_id = $event->id;
            $transaction->provider_reference = $session['subscription'] ?? null;
            $transaction->provider_customer_id = $session['customer'] ?? null;
            $transaction->currency = $session['currency'] ?? null;
            $transaction->amount = isset($session['amount_total']) ? $session['amount_total'] / 100 : null;
            $transaction->occurred_at = now();
            $transaction->save();
        });
    }
}
