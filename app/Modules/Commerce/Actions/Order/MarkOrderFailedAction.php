<?php

namespace App\Modules\Commerce\Actions\Order;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

/**
 * No Entitlement is ever created for a failed order — the counterpart to
 * MarkOrderPaidAction. Idempotent the same way (provider_event_id unique).
 */
class MarkOrderFailedAction
{
    public function handle(Order $order, ?string $stripeEventId = null, ?string $failureReason = null): void
    {
        if ($stripeEventId !== null && PaymentTransaction::query()->where('provider_event_id', $stripeEventId)->exists()) {
            return;
        }

        DB::transaction(function () use ($order, $stripeEventId, $failureReason): void {
            $order->status = OrderStatus::Failed;
            $order->save();

            $transaction = new PaymentTransaction;
            $transaction->order_id = $order->getKey();
            $transaction->type = PaymentTransactionType::Charge;
            $transaction->status = PaymentTransactionStatus::Failed;
            $transaction->provider_event_id = $stripeEventId;
            $transaction->amount = $order->total;
            $transaction->currency = $order->currency;
            $transaction->failure_reason = $failureReason;
            $transaction->occurred_at = now();
            $transaction->save();
        });
    }
}
