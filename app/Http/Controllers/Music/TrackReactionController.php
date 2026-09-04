<?php

namespace App\Http\Controllers\Music;

use App\Actions\Reaction\ToggleReactionAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing 🙌 reaction toggle for a Music Track — independent of
 * TrackReviewController's comment submission (client-confirmed,
 * 2026-09-02: a member reacts, comments, does both, or does neither). The
 * `auth` route middleware is the real server-side "guests cannot react"
 * gate (see routes/web.php). This route isn't under `/api`, so an
 * unauthenticated request still gets the normal HTML redirect-to-login
 * regardless of Accept header — bootstrap/app.php's shouldRenderJsonWhen
 * scopes JSON exception rendering to `api/*` only, site-wide. The frontend
 * fetch (resources/js/app.js) handles that via its own catch-all, not a
 * dedicated 401 branch.
 *
 * **Client-confirmed reversal (2026-09-02):** async fetch, not a full page
 * reload — this endpoint is dual-mode: a `wantsJson()` request (the real
 * fetch call in resources/js/app.js) gets `{reacted, count}` back; a plain
 * form submission (no JS) still gets the original redirect-back, so the
 * feature keeps working with JavaScript disabled.
 *
 * **Client-confirmed (2026-09-04):** gated by
 * config('features.music_reactions_enabled') — currently always true for
 * Music, kept for the same server-side-enforcement consistency as the other
 * two modules' reaction controllers.
 */
class TrackReactionController extends Controller
{
    public function toggle(Request $request, Track $track, ToggleReactionAction $toggle): JsonResponse|RedirectResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);
        abort_unless(config('features.music_reactions_enabled'), 404);

        $release = $track->release();
        abort_unless($release?->status === ReleaseStatus::Published, 404);

        /** @var User $user */
        $user = $request->user();

        $reacted = $toggle->handle($track, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'reacted' => $reacted,
                'count' => $track->reactions()->count(),
            ]);
        }

        return back();
    }
}
