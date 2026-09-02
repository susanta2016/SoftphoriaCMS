<?php

namespace App\Http\Controllers\Podcast;

use App\Actions\Reaction\ToggleReactionAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing 🙌 reaction toggle for a Podcast Episode — independent of
 * PodcastEpisodeReviewController's comment submission (client-confirmed,
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
 */
class PodcastEpisodeReactionController extends Controller
{
    public function toggle(Request $request, PodcastEpisode $episode, ToggleReactionAction $toggle): JsonResponse|RedirectResponse
    {
        abort_unless($episode->status === PodcastEpisodeStatus::Published, 404);

        /** @var User $user */
        $user = $request->user();

        $reacted = $toggle->handle($episode, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'reacted' => $reacted,
                'count' => $episode->reactions()->count(),
            ]);
        }

        return back();
    }
}
