<?php

namespace App\Modules\Podcast\Actions\Download;

use App\Enums\MediaCategory;
use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Support\DownloadAuthorizationResult;
use App\Modules\Podcast\Models\PodcastEpisode;

/**
 * Corrected rule (2026-09-01, superseding the earlier "active Pro Member
 * only" rule this class previously enforced): a Podcast Episode download is
 * FREE for any registered user — no Subscription, Entitlement, purchase, or
 * the Member Subscription feature flag is ever consulted. The previous rule
 * predated this correction and, given
 * config('features.member_subscription_enabled') defaults to false in
 * Phase 1, would have made every Podcast download permanently unreachable.
 * A guest is still denied — download access is the one place Podcast and
 * Music actually differ; watching an episode's YouTube video is free and
 * unrestricted for guests too.
 *
 * Mirrors AuthorizeTrackDownloadAction's shape: reuses the shared
 * DownloadLog/DownloadAuthorizationResult so Podcast downloads land in the
 * exact same download-history audit trail as Music's, rather than a second,
 * parallel mechanism — just with DownloadAccessType::Free and no
 * Entitlement/download-count branch, since episodes are never sold and
 * never limited.
 */
class AuthorizePodcastEpisodeDownloadAction
{
    public function authorizeForUser(PodcastEpisode $episode, User $user, ?string $ip = null, ?string $userAgent = null): DownloadAuthorizationResult
    {
        $media = $episode->audio;

        if ($media === null || $media->category() !== MediaCategory::Audio) {
            return $this->deny($episode, $user, 'no_audio_asset', $ip, $userAgent);
        }

        $this->log($episode, $user, DownloadLogStatus::Succeeded, null, $media->getKey(), $ip, $userAgent);

        return DownloadAuthorizationResult::granted($media, DownloadAccessType::Free);
    }

    private function deny(PodcastEpisode $episode, User $user, string $reason, ?string $ip, ?string $userAgent): DownloadAuthorizationResult
    {
        $this->log($episode, $user, DownloadLogStatus::Denied, $reason, null, $ip, $userAgent);

        return DownloadAuthorizationResult::denied($reason);
    }

    private function log(
        PodcastEpisode $episode,
        User $user,
        DownloadLogStatus $status,
        ?string $denialReason,
        ?int $mediaId,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $log = new DownloadLog;
        $log->user_id = $user->getKey();
        $log->entitlement_id = null;
        $log->access_type = $status === DownloadLogStatus::Succeeded ? DownloadAccessType::Free : null;
        $log->podcast_episode_id = $episode->getKey();
        $log->media_id = $mediaId;
        $log->status = $status;
        $log->denial_reason = $denialReason;
        $log->ip_address = $ip;
        $log->user_agent = $userAgent;
        $log->save();
    }
}
