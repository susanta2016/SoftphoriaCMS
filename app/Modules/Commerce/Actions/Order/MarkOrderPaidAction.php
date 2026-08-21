<?php

namespace App\Modules\Commerce\Actions\Order;

use App\Modules\Commerce\Actions\Entitlement\IssueEntitlementForOrderItemAction;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Support\IssuedEntitlement;
use Illuminate\Support\Facades\DB;

/**
 * The one place an Order transitions to paid and its Entitlement(s) come
 * into existence — called by the Stripe webhook handler
 * (HandleCheckoutSessionCompletedAction), and equally usable by a future
 * admin "mark as paid" override if one is ever approved. Idempotent: if
 * $stripeEventId has already been recorded in payment_transactions (a
 * webhook retry, which Stripe does routinely), this is a no-op — see
 * PaymentTransaction.provider_event_id's unique constraint.
 */
class MarkOrderPaidAction
{
    public function __construct(private readonly IssueEntitlementForOrderItemAction $issueEntitlement) {}

    /**
     * @return array<int, IssuedEntitlement>
     */
    public function handle(Order $order, string $stripePaymentIntentId, ?string $stripeEventId = null): array
    {
        if ($stripeEventId !== null && PaymentTransaction::query()->where('provider_event_id', $stripeEventId)->exists()) {
            return [];
        }

        return DB::transaction(function () use ($order, $stripePaymentIntentId, $stripeEventId): array {
            $order->status = OrderStatus::Paid;
            $order->paid_at = now();
            $order->stripe_payment_intent_id = $stripePaymentIntentId;
            $order->save();

            $transaction = new PaymentTransaction;
            $transaction->order_id = $order->getKey();
            $transaction->type = PaymentTransactionType::Charge;
            $transaction->status = PaymentTransactionStatus::Succeeded;
            $transaction->provider_event_id = $stripeEventId;
            $transaction->provider_reference = $stripePaymentIntentId;
            $transaction->amount = $order->total;
            $transaction->currency = $order->currency;
            $transaction->occurred_at = now();
            $transaction->save();

            return $order->items->map(fn ($item) => $this->issueEntitlement->handle($item))->all();
        });
    }
}
