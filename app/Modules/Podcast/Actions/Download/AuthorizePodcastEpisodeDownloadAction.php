<?php

namespace App\Modules\Podcast\Actions\Download;

use App\Models\User;
use App\Modules\Podcast\Models\PodcastEpisode;

/**
 * Client-confirmed rule (2026-08-24): only an active, paid Pro Member may
 * download a Podcast Episode's audio file. A free Member or a guest never
 * gets download access merely because the episode is available/streamable
 * — "can this episode be streamed" and "can this audio be downloaded" are
 * two different questions, and this class answers only the second one, so
 * it is never reused as (or driven by) whatever playback/streaming
 * authorization the future public Podcast pages add.
 *
 * "Active" reuses the existing Pro Membership rule verbatim —
 * User::hasActiveMembership() → Subscription::isActive() — so a member who
 * cancels keeps download access until their already-paid period actually
 * ends (App\Modules\Commerce\Models\Subscription's docblock). Episodes are
 * never sold individually (only Music is commerce-enabled — see the
 * Phase 1 master scope spec), so unlike AuthorizeTrackDownloadAction there
 * is no per-episode Entitlement/purchase branch to check.
 *
 * Deliberately reads Commerce's public User/Subscription API only — never
 * writes to Commerce's tables (DownloadLog, Entitlement, etc.) and adds no
 * route; this task's brief is admin-UI/authorization-rule only. No HTTP
 * route/controller consumes this yet — same "domain layer ready for a
 * future controller" shape as Commerce's AuthorizeTrackDownloadAction.
 */
class AuthorizePodcastEpisodeDownloadAction
{
    public function authorize(PodcastEpisode $episode, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($episode->audio === null) {
            return false;
        }

        return $user->hasActiveMembership();
    }
}
