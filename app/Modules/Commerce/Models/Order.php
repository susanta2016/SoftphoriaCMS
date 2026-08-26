<?php

namespace App\Modules\Commerce\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchase attempt (Single or Album), guest or registered — see
 * database/migrations/2026_08_23_090000_create_orders_table.php for the full
 * column rationale. purchaser_email is always set regardless of user_id, so
 * every order is searchable/displayable without joining to `users`.
 */
#[Fillable([
    'user_id', 'purchaser_email', 'purchaser_name', 'purchaser_phone', 'status', 'currency', 'subtotal', 'total',
    'payment_provider', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at',
])]
class Order extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function scopeStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
