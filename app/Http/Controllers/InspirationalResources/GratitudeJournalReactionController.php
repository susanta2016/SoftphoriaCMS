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
 * entry itself can be Public or Private (visibility column, simplified from
 * three states to two, 2026-09-10 — the previous "For Community" state was
 * removed) — only source = journal AND visibility = public rows ever appear
 * on the shared feed this button lives on, so both checks are enforced here
 * server-side, not just by what the feed happens to render. A registration
 * post and a Private journal entry (owner-only, in Account "Your Entries")
 * both 404 here even when targeted directly by public_id.
 *
 * The shared feed itself is open to guests (client-confirmed, 2026-09-10) —
 * this route carries no `auth` middleware either, so a guest reaches this
 * action rather than being redirected before it runs. Reacting still
 * requires an account: a guest is redirected to registration (not login,
 * per the client's explicit choice — a first-time visitor who wants to
 * react is a registration prospect, not a returning member), enforced here
 * so the endpoint is correct even if hit directly, not just via the feed's
 * own markup.
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
        abort_unless($lightPost->visibility === GratitudeJournalVisibility::Public, 404);

        if (! $request->user()) {
            return redirect()->route('register.show');
        }

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
