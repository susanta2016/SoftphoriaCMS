<?php

namespace App\Modules\Commerce\Actions\Download;

use App\Enums\MediaCategory;
use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Support\AlbumZipAuthorizationResult;
use App\Modules\Music\Models\Track;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The only way an Album purchase is downloaded (client-confirmed
 * 2026-10-29: a full-album purchase is ZIP-only — its entitlement never
 * authorizes individual track downloads; see
 * Entitlement::authorizesTrackDownload()). Only an Album *purchase*
 * entitlement qualifies — never a Single/Track entitlement, never Pro
 * Membership alone. Its counter is the entitlement's own
 * zip_downloads_used, limited by the snapshotted max_downloads and enforced
 * with the same single guarded UPDATE as AuthorizeTrackDownloadAction, so
 * it is independent of every individually purchased song's counter in the
 * same order.
 *
 * The archive only ever contains the tracks the entitlement itself covers
 * (Entitlement::tracks() — the purchased Album's own tracks, including any
 * pulled from sale after purchase), and only their real audio assets from
 * the private disk. It's built before the counter is consumed, so a build
 * failure never costs the purchaser a download; a cheap pre-check skips the
 * build when the limit is already reached, and the guarded counter UPDATE
 * stays the actual authority under concurrency.
 *
 * Every outcome writes exactly one DownloadLog row (album_id set,
 * track_id null).
 */
class AuthorizeAlbumZipDownloadAction
{
    public function authorizeForUser(Entitlement $entitlement, User $user, ?string $ip = null, ?string $userAgent = null): AlbumZipAuthorizationResult
    {
        $owned = $entitlement->user_id !== null && $entitlement->user_id === $user->getKey();

        return $this->finalize($entitlement, $owned, $user, $ip, $userAgent);
    }

    public function authorizeForGuest(Entitlement $entitlement, string $suppliedToken, ?string $ip = null, ?string $userAgent = null): AlbumZipAuthorizationResult
    {
        $owned = $entitlement->access_token_hash !== null
            && hash_equals($entitlement->access_token_hash, hash('sha256', $suppliedToken));

        return $this->finalize($entitlement, $owned, null, $ip, $userAgent);
    }

    private function finalize(Entitlement $entitlement, bool $owned, ?User $user, ?string $ip, ?string $userAgent): AlbumZipAuthorizationResult
    {
        if (! $owned || $entitlement->album_id === null || $entitlement->isRevoked() || $entitlement->isExpired()) {
            return $this->deny($entitlement, $user, 'not_entitled', $ip, $userAgent);
        }

        $tracks = $this->downloadableTracks($entitlement);

        if ($tracks->isEmpty()) {
            return $this->deny($entitlement, $user, 'no_audio_asset', $ip, $userAgent);
        }

        if ($entitlement->refresh()->isExhausted()) {
            return $this->deny($entitlement, $user, 'limit_reached', $ip, $userAgent);
        }

        $zipPath = $this->buildZip($tracks);

        if ($zipPath === null) {
            return $this->deny($entitlement, $user, 'no_audio_asset', $ip, $userAgent);
        }

        $consumed = DB::table('entitlements')
            ->where('id', $entitlement->getKey())
            ->where(fn ($query) => $query->whereNull('max_downloads')->orWhereColumn('zip_downloads_used', '<', 'max_downloads'))
            ->update(['zip_downloads_used' => DB::raw('zip_downloads_used + 1')]);

        if ($consumed === 0) {
            @unlink($zipPath);

            return $this->deny($entitlement, $user, 'limit_reached', $ip, $userAgent);
        }

        $this->log($entitlement, $user, DownloadLogStatus::Succeeded, null, $ip, $userAgent);

        return AlbumZipAuthorizationResult::granted($zipPath, $this->zipFilename($entitlement));
    }

    /**
     * @return Collection<int, Track>
     */
    private function downloadableTracks(Entitlement $entitlement): Collection
    {
        return $entitlement->tracks()
            ->load('audio')
            ->filter(fn (Track $track) => $track->audio !== null
                && $track->audio->category() === MediaCategory::Audio
                && Storage::disk($track->audio->disk)->exists($track->audio->path))
            ->sortBy(fn (Track $track) => [$track->track_number ?? PHP_INT_MAX, $track->getKey()])
            ->values();
    }

    /**
     * Audio is already compressed, so entries are stored, not deflated —
     * same size, far less CPU. Entry names are "NN - Title.ext", de-duplicated
     * so two identically-titled tracks never overwrite each other.
     *
     * @param  Collection<int, Track>  $tracks
     */
    private function buildZip(Collection $tracks): ?string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'album-zip-');
        $zip = new ZipArchive;

        if ($zipPath === false || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $usedNames = [];

        foreach ($tracks as $index => $track) {
            $media = $track->audio;
            $extension = pathinfo($media->original_filename ?: $media->path, PATHINFO_EXTENSION) ?: 'mp3';
            $title = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', '', (string) $track->title)) ?: 'Track';
            $name = sprintf('%02d - %s.%s', $track->track_number ?? $index + 1, $title, strtolower($extension));

            for ($suffix = 2; isset($usedNames[strtolower($name)]); $suffix++) {
                $name = sprintf('%02d - %s (%d).%s', $track->track_number ?? $index + 1, $title, $suffix, strtolower($extension));
            }

            $usedNames[strtolower($name)] = true;

            $zip->addFile(Storage::disk($media->disk)->path($media->path), $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }

        if (! $zip->close()) {
            @unlink($zipPath);

            return null;
        }

        return $zipPath;
    }

    private function zipFilename(Entitlement $entitlement): string
    {
        return (Str::slug((string) $entitlement->album?->title) ?: 'album').'.zip';
    }

    private function deny(Entitlement $entitlement, ?User $user, string $reason, ?string $ip, ?string $userAgent): AlbumZipAuthorizationResult
    {
        $this->log($entitlement, $user, DownloadLogStatus::Denied, $reason, $ip, $userAgent);

        return AlbumZipAuthorizationResult::denied($reason);
    }

    private function log(Entitlement $entitlement, ?User $user, DownloadLogStatus $status, ?string $denialReason, ?string $ip, ?string $userAgent): void
    {
        $log = new DownloadLog;
        $log->user_id = $user?->getKey();
        $log->entitlement_id = $entitlement->getKey();
        $log->access_type = DownloadAccessType::Purchase;
        $log->album_id = $entitlement->album_id;
        $log->status = $status;
        $log->denial_reason = $denialReason;
        $log->ip_address = $ip;
        $log->user_agent = $userAgent;
        $log->save();
    }
}
