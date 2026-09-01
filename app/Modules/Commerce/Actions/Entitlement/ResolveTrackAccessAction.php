<?php

namespace App\Modules\Commerce\Actions\Entitlement;

use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Support\AccessGrant;
use App\Modules\Music\Models\Track;

/**
 * Read-only: given a Track and either an authenticated User or a guest
 * token, decides whether download access exists right now and why —
 * checked, in order, active Subscription (membership — the whole
 * catalogue), then a matching non-revoked/non-expired Entitlement.
 * Deliberately does NOT also reject an already-exhausted entitlement here:
 * download-count enforcement has to be a single atomic guarded UPDATE to be
 * race-safe (see AuthorizeTrackDownloadAction), so checking it a second time
 * non-atomically at this stage would just report the wrong denial reason
 * ("not entitled" instead of "limit reached") once a limit is hit, without
 * adding any actual protection. Reused from the future authenticated
 * download endpoint, the future guest download endpoint, and Admin's
 * Entitlement/Download views — one implementation, per §10's "never trust
 * ... without server-side authorization and resolution."
 */
class ResolveTrackAccessAction
{
    public function forUser(Track $track, User $user): ?AccessGrant
    {
        if ($user->hasActiveMembership()) {
            return new AccessGrant(DownloadAccessType::Membership, null);
        }

        $entitlement = Entitlement::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->where('track_id', $track->getKey())
                ->when($track->album_id !== null, fn ($q) => $q->orWhere('album_id', $track->album_id))
                ->when($track->single_id !== null, fn ($q) => $q->orWhere('single_id', $track->single_id)))
            ->get()
            ->first(fn (Entitlement $entitlement) => $entitlement->coversTrack($track) && ! $entitlement->isRevoked() && ! $entitlement->isExpired());

        return $entitlement === null ? null : new AccessGrant(DownloadAccessType::Purchase, $entitlement);
    }

    /**
     * Guests never have a Subscription (§4/§12: Pro Membership requires an
     * account) — only a matching Entitlement, verified by a constant-time
     * comparison of the supplied token's hash against the stored one.
     */
    public function forGuestToken(Track $track, string $entitlementPublicId, string $suppliedToken): ?AccessGrant
    {
        $entitlement = Entitlement::query()->where('public_id', $entitlementPublicId)->first();

        if ($entitlement === null || $entitlement->access_token_hash === null) {
            return null;
        }

        if (! hash_equals($entitlement->access_token_hash, hash('sha256', $suppliedToken))) {
            return null;
        }

        if (! $entitlement->coversTrack($track) || $entitlement->isRevoked() || $entitlement->isExpired()) {
            return null;
        }

        return new AccessGrant(DownloadAccessType::Purchase, $entitlement);
    }
}
