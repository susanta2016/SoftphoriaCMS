<?php

namespace App\Modules\Commerce\Actions\Download;

use App\Enums\MediaCategory;
use App\Models\User;
use App\Modules\Commerce\Actions\Entitlement\ResolveTrackAccessAction;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Support\AccessGrant;
use App\Modules\Commerce\Support\DownloadAuthorizationResult;
use App\Modules\Music\Models\Track;
use Illuminate\Support\Facades\DB;

/**
 * §13 of the approved brief's full server-side chain: resolve the grant
 * (ResolveTrackAccessAction), re-validate the Track actually has a real
 * audio asset, atomically enforce the remaining-downloads limit, then write
 * exactly one DownloadLog row for the outcome — success or denial, always.
 * No HTTP route/controller consumes this yet (§11: no public download route
 * in this task) — it's the domain layer a future download endpoint calls
 * directly, fully exercised by tests without one.
 *
 * The download counter is enforced with a single guarded UPDATE
 * (`downloads_used = downloads_used + 1 WHERE ... AND (max_downloads IS
 * NULL OR downloads_used < max_downloads)`), not a read-then-write — two
 * concurrent requests against a `max_downloads = 1` entitlement can only
 * ever result in one affected row, closing the race a naive read-check-
 * increment sequence would leave open (§13/§22: "download counter cannot be
 * bypassed").
 */
class AuthorizeTrackDownloadAction
{
    public function __construct(private readonly ResolveTrackAccessAction $resolveAccess) {}

    public function authorizeForUser(Track $track, User $user, ?string $ip = null, ?string $userAgent = null): DownloadAuthorizationResult
    {
        $grant = $this->resolveAccess->forUser($track, $user);

        return $this->finalize($track, $grant, $user, $ip, $userAgent);
    }

    public function authorizeForGuest(
        Track $track,
        string $entitlementPublicId,
        string $token,
        ?string $ip = null,
        ?string $userAgent = null,
    ): DownloadAuthorizationResult {
        $grant = $this->resolveAccess->forGuestToken($track, $entitlementPublicId, $token);

        return $this->finalize($track, $grant, null, $ip, $userAgent);
    }

    private function finalize(Track $track, ?AccessGrant $grant, ?User $user, ?string $ip, ?string $userAgent): DownloadAuthorizationResult
    {
        if ($grant === null) {
            return $this->deny($track, $user, null, null, 'not_entitled', $ip, $userAgent);
        }

        $media = $track->audio;

        if ($media === null || $media->category() !== MediaCategory::Audio) {
            return $this->deny($track, $user, $grant->entitlement, $grant->type, 'no_audio_asset', $ip, $userAgent);
        }

        if ($grant->type === DownloadAccessType::Purchase) {
            $incremented = DB::table('entitlements')
                ->where('id', $grant->entitlement->getKey())
                ->where(fn ($query) => $query->whereNull('max_downloads')->orWhereColumn('downloads_used', '<', 'max_downloads'))
                ->update(['downloads_used' => DB::raw('downloads_used + 1')]);

            if ($incremented === 0) {
                return $this->deny($track, $user, $grant->entitlement, $grant->type, 'limit_reached', $ip, $userAgent);
            }
        }

        $this->log($track, $user, $grant->entitlement, $grant->type, DownloadLogStatus::Succeeded, null, $media->getKey(), $ip, $userAgent);

        return DownloadAuthorizationResult::granted($media, $grant->type);
    }

    private function deny(
        Track $track,
        ?User $user,
        ?Entitlement $entitlement,
        ?DownloadAccessType $accessType,
        string $reason,
        ?string $ip,
        ?string $userAgent,
    ): DownloadAuthorizationResult {
        $this->log($track, $user, $entitlement, $accessType, DownloadLogStatus::Denied, $reason, null, $ip, $userAgent);

        return DownloadAuthorizationResult::denied($reason);
    }

    private function log(
        Track $track,
        ?User $user,
        ?Entitlement $entitlement,
        ?DownloadAccessType $accessType,
        DownloadLogStatus $status,
        ?string $denialReason,
        ?int $mediaId,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $log = new DownloadLog;
        $log->user_id = $user?->getKey();
        $log->entitlement_id = $entitlement?->getKey();
        $log->access_type = $accessType;
        $log->track_id = $track->getKey();
        $log->media_id = $mediaId;
        $log->status = $status;
        $log->denial_reason = $denialReason;
        $log->ip_address = $ip;
        $log->user_agent = $userAgent;
        $log->save();
    }
}
