<?php

namespace App\Http\Controllers\Music;

use App\Actions\Review\SubmitReviewAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing comment submission for a Music Track — the single
 * submission endpoint for both a Single's listening page and an Album-owned
 * track's own page (both ultimately comment on the same underlying Track
 * row; see Track::reviewUrl()). Mirrors PodcastEpisodeReviewController/
 * PoetryProseReviewController exactly: the `auth` route middleware is the
 * real server-side "guests cannot submit" gate (see routes/web.php), and
 * all persistence/moderation logic is delegated to the same shared
 * App\Actions\Review\SubmitReviewAction — never a Music-specific copy.
 *
 * **Client-confirmed reversal (2026-09-02):** no longer collects a star
 * rating — see SubmitReviewAction's own docblock. The honeypot check below
 * reuses ContactController::store()'s exact pattern (same field name,
 * same silent-success-on-trip behavior) rather than a new spam-prevention
 * mechanism.
 *
 * **Client-confirmed (2026-09-04):** internal comments are disabled for
 * Music — gated server-side by config('features.music_comments_enabled'),
 * not just a hidden form, so a direct POST is rejected the same as a
 * genuinely unpublished track. The independent 🙌 reaction
 * (TrackReactionController) stays enabled.
 */
class TrackReviewController extends Controller
{
    public function store(Request $request, Track $track, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);
        abort_unless(config('features.music_comments_enabled'), 404);

        $release = $track->release();
        abort_unless($release?->status === ReleaseStatus::Published, 404);

        // A real visitor never sees or fills in this field — see the
        // honeypot markup in resources/views/music/listening.blade.php.
        // A bot that blindly fills every input trips it, and the request
        // is discarded silently: same redirect/success message as a
        // genuine submission, so the bot gets no signal it was caught.
        if (filled($request->input('hp_website'))) {
            return back()->with('review_status', 'Thank you — your comment has been submitted.');
        }

        // Trimmed before validation so a whitespace-only submission (which
        // the JS guard in app.js can't catch if JavaScript is disabled)
        // fails the `required` rule below rather than being accepted as a
        // blank comment.
        $request->merge(['content' => trim((string) $request->input('content'))]);

        $maxLength = config('reviews.max_length');

        $data = $request->validate([
            'content' => ['required', 'string', "max:{$maxLength}"],
        ], [
            'content.required' => 'Please write a few words before submitting your comment.',
            'content.max' => "Comments can be at most {$maxLength} characters.",
        ]);

        /** @var User $user */
        $user = $request->user();

        $submit->handle($track, $user, $data['content']);

        return back()->with('review_status', 'Thank you — your comment has been submitted.');
    }
}
