<?php

namespace App\Modules\Commerce\Actions\Webhook;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Support\StripeEvent;
use Illuminate\Support\Facades\DB;

/**
 * Handles Stripe's `charge.refunded`. Deliberately does NOT auto-revoke the
 * Entitlement — this is the one open decision flagged in the final report
 * (refund policy wasn't specified in the approved brief). The refund is
 * fully recorded in the ledger and visible on OrderResource; revoking access
 * stays an explicit admin "Revoke Access" action
 * (RevokeEntitlementAction) until/unless auto-revocation is approved.
 */
class HandleChargeRefundedAction
{
    public function handle(StripeEvent $event): void
    {
        if (PaymentTransaction::query()->where('provider_event_id', $event->id)->exists()) {
            return;
        }

        $charge = $event->data;
        $order = isset($charge['payment_intent'])
            ? Order::query()->where('stripe_payment_intent_id', $charge['payment_intent'])->first()
            : null;

        if ($order === null) {
            return;
        }

        DB::transaction(function () use ($order, $charge, $event): void {
            $order->status = OrderStatus::Refunded;
            $order->save();

            $transaction = new PaymentTransaction;
            $transaction->order_id = $order->getKey();
            $transaction->type = PaymentTransactionType::Refund;
            $transaction->status = PaymentTransactionStatus::Succeeded;
            $transaction->provider_event_id = $event->id;
            $transaction->provider_reference = $charge['id'] ?? null;
            $transaction->amount = isset($charge['amount_refunded']) ? $charge['amount_refunded'] / 100 : null;
            $transaction->currency = $charge['currency'] ?? null;
            $transaction->occurred_at = now();
            $transaction->save();
        });
    }
}
