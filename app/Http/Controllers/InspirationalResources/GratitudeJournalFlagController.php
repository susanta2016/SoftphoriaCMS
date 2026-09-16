<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Actions\GratitudeJournal\FlagGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Http\Controllers\Controller;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The 🚩 "report this entry" action on a Gratitude Journal shared-feed
 * entry — independent of GratitudeJournalReactionController's 🙌 (a member
 * can react and/or report the same entry, independently). Mirrors that
 * controller's exact source/visibility guard and its open-to-guests route
 * with a controller-level redirect to registration (not login) for anyone
 * without an account — same client-confirmed reasoning: a first-time
 * visitor who wants to report is a registration prospect, not a returning
 * member. One-shot like PoetryProseReviewFlagController::store(): a
 * `wantsJson()` request gets `{flagged, already}` back; a plain form
 * submission gets the original redirect-back, so the feature keeps
 * working with JavaScript disabled.
 *
 * Gated by config('features.gratitude_journal_flags_enabled') — code
 * default false, matching gratitude_journal_reactions_enabled; only this
 * environment's .env explicitly enables it.
 */
class GratitudeJournalFlagController extends Controller
{
    public function store(Request $request, LightPost $lightPost, FlagGratitudeJournalEntryAction $flag): JsonResponse|RedirectResponse
    {
        abort_unless(config('features.gratitude_journal_flags_enabled'), 404);
        abort_unless($lightPost->source === LightPostSource::Journal, 404);
        abort_unless($lightPost->visibility === GratitudeJournalVisibility::Public, 404);

        if (! $request->user()) {
            return redirect()->route('register.show');
        }

        /** @var User $user */
        $user = $request->user();

        $created = $flag->handle($lightPost, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'flagged' => true,
                'already' => ! $created,
            ]);
        }

        return back()->with('gratitude_journal_status', 'Thank you — this entry has been reported for review.');
    }
}
