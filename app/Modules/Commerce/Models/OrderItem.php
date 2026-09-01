<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Exceptions\InvalidOrderItemPurchasableException;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Shared\Concerns\BelongsToExactlyOneOf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Throwable;

/**
 * The purchased line — see database/migrations/2026_08_23_090001_create_order_items_table.php
 * for the full column rationale (historical snapshot, dual-nullable FK
 * instead of a morph column). itemType()/itemLabel() are the "generic
 * commerce naming" surface a future non-Music digital product would plug
 * into — the underlying storage stays album_id/single_id/track_id, matching
 * the rest of this codebase's convention rather than introducing polymorphism
 * for a small, fixed set of parent types. track_id (added later, see
 * 2026_09_01_100000_add_track_id_to_order_items_table.php) lets a single
 * Album-owned Track be bought on its own — never the whole Album — at the
 * same GlobalPricingResolver::perSongPrice() a Single already uses.
 */
#[Fillable(['order_id', 'album_id', 'single_id', 'track_id', 'item_title', 'quantity', 'unit_price', 'currency', 'subtotal', 'total'])]
class OrderItem extends Model
{
    use BelongsToExactlyOneOf;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function exactlyOneOfColumns(): array
    {
        return ['album_id', 'single_id', 'track_id'];
    }

    public function exactlyOneOfException(): Throwable
    {
        return InvalidOrderItemPurchasableException::mustReferenceExactlyOne();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * withTrashed(): a release soft-deleted after being sold must still
     * resolve here for historical order display.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class)->withTrashed();
    }

    public function single(): BelongsTo
    {
        return $this->belongsTo(Single::class)->withTrashed();
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class)->withTrashed();
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }

    /**
     * @return 'album'|'single'|'track'
     */
    public function itemType(): string
    {
        return match (true) {
            $this->album_id !== null => 'album',
            $this->single_id !== null => 'single',
            default => 'track',
        };
    }

    public function item(): Album|Single|Track|null
    {
        return $this->album ?: $this->single ?: $this->track;
    }
}
