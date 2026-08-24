<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Handles Stripe's `invoice.payment_succeeded` — a Pro Member's *monthly
 * renewal* charge succeeding (distinct from the first payment at signup,
 * already recorded by HandleCheckoutSessionCompletedAction's subscription
 * branch). Records the ledger row only; the resulting period
 * extension/status is left to the `customer.subscription.updated` event
 * Stripe always sends alongside this one
 * (HandleSubscriptionUpdatedAction) rather than this handler guessing or
 * duplicating that transition — same split as
 * HandleInvoicePaymentFailedAction. Without this handler, a successful
 * renewal left no payment-ledger trace at all, only the (now-extended)
 * subscription period — this closes that gap so "last payment" on
 * SubscriptionResource reflects renewals, not only the original signup.
 */
class HandleInvoicePaymentSucceededAction
{
    public function handle(StripeEvent $event): void
    {
        if (PaymentTransaction::query()->where('provider_event_id', $event->id)->exists()) {
            return;
        }

        $invoice = $event->data;
        $subscription = isset($invoice['subscription'])
            ? Subscription::query()->where('stripe_subscription_id', $invoice['subscription'])->first()
            : null;

        if ($subscription === null) {
            return;
        }

        $transaction = new PaymentTransaction;
        $transaction->subscription_id = $subscription->getKey();
        $transaction->type = PaymentTransactionType::SubscriptionInvoicePaid;
        $transaction->status = PaymentTransactionStatus::Succeeded;
        $transaction->provider_event_id = $event->id;
        $transaction->provider_reference = $invoice['id'] ?? null;
        $transaction->provider_customer_id = $invoice['customer'] ?? null;
        $transaction->amount = isset($invoice['amount_paid']) ? $invoice['amount_paid'] / 100 : null;
        $transaction->currency = $invoice['currency'] ?? null;
        $transaction->occurred_at = now();

        try {
            $transaction->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of this same event id (Stripe retries,
            // or the CLI forwarding both the normal and Connect copy of a
            // triggered event) already recorded it between the exists()
            // check above and this insert — nothing left to do.
        }
    }
}
