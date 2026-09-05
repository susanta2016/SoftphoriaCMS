<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Actions\Reaction\ToggleReactionAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Http\Controllers\Controller;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The 🙌 reaction toggle for a Gratitude Journal entry on the shared member
 * feed (GratitudeJournalFeedController, /inspirational-resources/gratitude-journal)
 * — mirrors TrackReactionController/PodcastEpisodeReactionController/
 * PoetryProseReactionController's exact shape, reusing the same generic
 * App\Models\Reaction / App\Actions\Reaction\ToggleReactionAction.
 *
 * App\Models\LightPost is shared by registration-time "Leave a Little
 * Light" posts and Gratitude Journal entries (source column), and a journal
 * entry itself can be Public, Private, or Community (visibility column) —
 * only source = journal AND visibility = community rows ever appear on the
 * shared feed this button lives on, so both checks are enforced here
 * server-side, not just by what the feed happens to render. A registration
 * post, a Public journal entry (its own exposure is the homepage carousel),
 * and a Private journal entry (owner-only, in Account "Your Entries") all
 * 404 here even when targeted directly by public_id.
 *
 * The `auth` + EnsureAccountIsUsable route middleware (see routes/web.php)
 * is the real server-side "guests cannot react" gate — this page itself is
 * already fully auth-gated (no guest ever reaches the feed to see this
 * button), but the endpoint enforces it independently regardless.
 *
 * Dual-mode response, same as every other reaction controller: a
 * `wantsJson()` request (the real fetch call in resources/js/app.js,
 * data-reaction-*) gets `{reacted, count}` back; a plain form submission
 * (no JS) gets the original redirect-back, so the feature keeps working
 * with JavaScript disabled.
 *
 * Gated by config('features.gratitude_journal_reactions_enabled') — unlike
 * Music/Podcast (default true), this defaults to false; only this
 * environment's .env explicitly enables it.
 */
class GratitudeJournalReactionController extends Controller
{
    public function toggle(Request $request, LightPost $lightPost, ToggleReactionAction $toggle): JsonResponse|RedirectResponse
    {
        abort_unless(config('features.gratitude_journal_reactions_enabled'), 404);
        abort_unless($lightPost->source === LightPostSource::Journal, 404);
        abort_unless($lightPost->visibility === GratitudeJournalVisibility::Community, 404);

        /** @var User $user */
        $user = $request->user();

        $reacted = $toggle->handle($lightPost, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'reacted' => $reacted,
                'count' => $lightPost->reactions()->count(),
            ]);
        }

        return back();
    }
}
