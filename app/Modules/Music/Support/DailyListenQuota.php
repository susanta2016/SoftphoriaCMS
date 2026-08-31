<?php

namespace App\Modules\Music\Support;

use App\Modules\Music\Models\TrackListen;

/**
 * The single source of truth for "how many whole-song listens has this
 * registered user completed today, against the configured daily limit"
 * (features.registered_user_whole_song_listens_per_day) — shared by
 * TrackStreamController (denies streaming once reached),
 * TrackListenController (reports the just-updated state back to the client
 * so app.js can react immediately, without a second round trip), and
 * MusicController (bakes the state into the rendered page). Extracted
 * 2026-08-31 so these three no longer independently duplicate the same
 * query — a real risk of drifting out of sync, which is exactly the class
 * of bug this fixes.
 */
class DailyListenQuota
{
    /**
     * @return array{count: int, limit: int, reached: bool}
     */
    public function check(int $userId): array
    {
        $limit = (int) config('features.registered_user_whole_song_listens_per_day');

        $count = TrackListen::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return [
            'count' => $count,
            'limit' => $limit,
            'reached' => $count >= $limit,
        ];
    }
}
