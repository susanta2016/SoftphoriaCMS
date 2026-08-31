<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Models\TrackListen;
use App\Modules\Music\Support\DailyListenQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The sole writer of track_listens — a server-authoritative record of one
 * completed whole-track playback by an authenticated user, and (via
 * DailyListenQuota) the same computation TrackStreamController's
 * daily-quota check uses. The frontend calls this only on the <audio>
 * element's native `ended` event (resources/js/app.js), never on
 * pause/seek/timeupdate, so an incomplete or abandoned playback is never
 * counted. Trusting this one client-reported signal (a malicious client
 * could technically fire it without real playback) is an accepted
 * tradeoff for a soft daily usage cap, not a security/purchase boundary —
 * unlike download authorization, which this never touches.
 *
 * The response reports whether this completion just reached the daily
 * limit (limit_reached) purely so app.js can immediately stop the player
 * on the same page (2026-08-31 fix for stale client-side state after the
 * Nth listen) — TrackStreamController remains the actual enforcement
 * point on every subsequent stream request regardless of what the client
 * does with this value.
 */
class TrackListenController extends Controller
{
    public function __invoke(Request $request, Track $track, DailyListenQuota $quota): JsonResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);

        $user = $request->user();

        TrackListen::query()->create([
            'user_id' => $user->id,
            'track_id' => $track->id,
        ]);

        $state = $quota->check($user->id);

        return response()->json([
            'status' => 'recorded',
            'listens_today' => $state['count'],
            'daily_limit' => $state['limit'],
            'limit_reached' => $state['reached'],
        ]);
    }
}
