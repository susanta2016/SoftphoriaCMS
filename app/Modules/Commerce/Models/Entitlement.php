<?php

namespace App\Modules\Commerce\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\Commerce\Enums\EntitlementStatus;
use App\Modules\Commerce\Exceptions\InvalidEntitlementPurchasableException;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Shared\Concerns\BelongsToExactlyOneOf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Throwable;

/**
 * What a purchaser is allowed to download, granted from exactly one paid
 * OrderItem — see database/migrations/2026_08_23_090003_create_entitlements_table.php
 * for the full column rationale. Pro Member (subscription) access is
 * deliberately not represented as rows here; see Subscription's docblock.
 */
#[Fillable([
    'order_item_id', 'user_id', 'purchaser_email', 'album_id', 'single_id',
    'access_token_hash', 'max_downloads', 'downloads_used', 'expires_at',
])]
class Entitlement extends Model
{
    use BelongsToExactlyOneOf, HasPublicId;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function exactlyOneOfColumns(): array
    {
        return ['album_id', 'single_id'];
    }

    public function exactlyOneOfException(): Throwable
    {
        return InvalidEntitlementPurchasableException::mustReferenceExactlyOne();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class)->withTrashed();
    }

    public function single(): BelongsTo
    {
        return $this->belongsTo(Single::class)->withTrashed();
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    /**
     * Whichever Track(s) this grants access to — an Album entitlement covers
     * every track under it (including trashed ones: a track pulled from sale
     * after purchase must not retroactively break an existing customer's
     * access to what they already paid for), a Single entitlement covers
     * exactly its one track.
     *
     * @return Collection<int, Track>
     */
    public function tracks(): Collection
    {
        if ($this->album_id !== null) {
            return Track::withTrashed()->where('album_id', $this->album_id)->get();
        }

        return Track::withTrashed()->where('single_id', $this->single_id)->get();
    }

    public function coversTrack(Track $track): bool
    {
        return ($this->album_id !== null && $track->album_id === $this->album_id)
            || ($this->single_id !== null && $track->single_id === $this->single_id);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_downloads !== null && $this->downloads_used >= $this->max_downloads;
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function remainingDownloads(): ?int
    {
        return $this->max_downloads === null ? null : max(0, $this->max_downloads - $this->downloads_used);
    }

    /**
     * Computed display value — see EntitlementStatus's docblock for why this
     * is never a stored column.
     */
    public function status(): EntitlementStatus
    {
        return match (true) {
            $this->isRevoked() => EntitlementStatus::Revoked,
            $this->isExpired() => EntitlementStatus::Expired,
            $this->isExhausted() => EntitlementStatus::Exhausted,
            default => EntitlementStatus::Active,
        };
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
