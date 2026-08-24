<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Actions\Registration\SendProRegistrationWelcomeEmailAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Handles Stripe's `checkout.session.completed`, for both modes: `payment`
 * (a Single/Album Order) and `subscription` (Pro Member registration via
 * RegisterProUserAction's embedded Checkout Session). Looks the Order up by
 * `stripe_checkout_session_id` (set when the session was created) — see
 * StripeGateway::createCheckoutSessionForOrder()'s `client_reference_id`/
 * metadata, which a future checkout controller stamps onto the Order before
 * redirecting.
 */
class HandleCheckoutSessionCompletedAction
{
    public function __construct(
        private readonly MarkOrderPaidAction $markOrderPaid,
        private readonly SendProRegistrationWelcomeEmailAction $sendProWelcomeEmail,
    ) {}

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

        $wasJustRegistered = false;

        // Stripe (and the CLI's own Connect-event forwarding) can deliver
        // the same event id twice in close succession; the exists() check
        // above is a plain read and doesn't close that race. A concurrent
        // duplicate hits payment_transactions' provider_event_id unique
        // constraint here instead of silently double-processing — treat
        // that exactly like the exists() check catching it first, rather
        // than letting it surface as an unhandled 500 to Stripe.
        try {
            $subscription = DB::transaction(function () use ($session, $event, $userId, &$wasJustRegistered): Subscription {
                $subscription = Subscription::query()->firstOrNew(['user_id' => (int) $userId]);
                $wasJustRegistered = ! $subscription->exists;

                $subscription->stripe_customer_id = $session['customer'] ?? $subscription->stripe_customer_id;
                $subscription->stripe_subscription_id = $session['subscription'] ?? $subscription->stripe_subscription_id;
                $subscription->status = SubscriptionStatus::Active;
                $subscription->started_at ??= now();

                // Snapshot what Stripe actually charged for this signup — the
                // same amount_total/currency the PaymentTransaction row below
                // already records — rather than re-resolving GlobalPricingResolver
                // a second time, which could have changed since the Checkout
                // Session was created.
                if ($wasJustRegistered) {
                    $subscription->currency = $session['currency'] ?? null;
                    $subscription->price_at_subscription = isset($session['amount_total']) ? $session['amount_total'] / 100 : null;
                }

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

                return $subscription;
            });
        } catch (UniqueConstraintViolationException) {
            return;
        }

        // Outside the transaction so a mail failure/exception can never roll
        // back the already-confirmed Subscription — and only on the first
        // time this user's Pro signup is ever confirmed, never on a
        // renewal-adjacent replay (point 5 of the confirmed spec: the Pro
        // confirmation email fires only after confirmed payment, exactly
        // once).
        if ($wasJustRegistered) {
            $this->sendProWelcomeEmail->handle($subscription->user);
        }
    }
}
