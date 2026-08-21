<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only ledger of monetary Stripe events — see
 * database/migrations/2026_08_23_090006_create_payment_transactions_table.php
 * for the full rationale. Never carries card/CVV/raw payment credentials —
 * only Stripe's own provider-side references (§10/§22 of the approved
 * brief).
 */
#[Fillable([
    'order_id', 'subscription_id', 'type', 'status', 'provider', 'provider_event_id',
    'provider_reference', 'provider_customer_id', 'amount', 'currency', 'failure_reason',
    'occurred_at', 'metadata',
])]
class PaymentTransaction extends Model
{
    protected static function booted(): void
    {
        static::saving(function (PaymentTransaction $transaction): void {
            if ($transaction->order_id === null && $transaction->subscription_id === null) {
                throw new RuntimeException('A payment transaction must relate to an order or a subscription (or both) — never neither.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentTransactionType::class,
            'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
