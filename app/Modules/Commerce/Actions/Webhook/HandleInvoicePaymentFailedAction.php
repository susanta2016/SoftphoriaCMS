<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Handles Stripe's `invoice.payment_failed` — records the failure in the
 * ledger only. The resulting status change (e.g. to `past_due`) is left to
 * the `customer.subscription.updated` event Stripe always sends alongside
 * this one, rather than this handler guessing/duplicating that transition.
 */
class HandleInvoicePaymentFailedAction
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
        $transaction->type = PaymentTransactionType::SubscriptionInvoiceFailed;
        $transaction->status = PaymentTransactionStatus::Failed;
        $transaction->provider_event_id = $event->id;
        $transaction->provider_reference = $invoice['id'] ?? null;
        $transaction->provider_customer_id = $invoice['customer'] ?? null;
        $transaction->amount = isset($invoice['amount_due']) ? $invoice['amount_due'] / 100 : null;
        $transaction->currency = $invoice['currency'] ?? null;
        $transaction->failure_reason = $invoice['last_finalization_error']['message'] ?? null;
        $transaction->occurred_at = now();

        try {
            $transaction->save();
        } catch (UniqueConstraintViolationException) {
            // See HandleInvoicePaymentSucceededAction — a concurrent
            // delivery of this same event id already recorded it.
        }
    }
}
