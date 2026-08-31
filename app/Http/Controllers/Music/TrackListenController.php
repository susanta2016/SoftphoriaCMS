<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Models\TrackListen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The sole writer of track_listens — a server-authoritative record of one
 * completed whole-track playback by an authenticated user, and the only
 * input to TrackStreamController's daily-quota check. The frontend calls
 * this only on the <audio> element's native `ended` event (resources/js/
 * app.js), never on pause/seek/timeupdate, so an incomplete or abandoned
 * playback is never counted. Trusting this one client-reported signal (a
 * malicious client could technically fire it without real playback) is an
 * accepted tradeoff for a soft daily usage cap, not a security/purchase
 * boundary — unlike download authorization, which this never touches.
 */
class TrackListenController extends Controller
{
    public function __invoke(Request $request, Track $track): JsonResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);

        TrackListen::query()->create([
            'user_id' => $request->user()->id,
            'track_id' => $track->id,
        ]);

        return response()->json(['status' => 'recorded']);
    }
}
