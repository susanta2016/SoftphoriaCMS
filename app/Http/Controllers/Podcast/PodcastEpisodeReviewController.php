<?php

namespace App\Http\Controllers\Podcast;

use App\Actions\Review\SubmitReviewAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing comment submission for a Podcast Episode — the `auth`
 * route middleware is the real server-side "guests cannot submit" gate (see
 * routes/web.php), matching the Download endpoint's pattern; hiding the
 * form for a guest in Blade is only a UI nicety on top of it. Delegates all
 * actual persistence/moderation logic to the shared, reusable
 * App\Actions\Review\SubmitReviewAction — Music and Poetry/Prose call the
 * exact same action from their own controllers.
 *
 * **Client-confirmed reversal (2026-09-02):** no longer collects a star
 * rating — see SubmitReviewAction's own docblock. The honeypot check below
 * reuses ContactController::store()'s exact pattern (same field name,
 * same silent-success-on-trip behavior) rather than a new spam-prevention
 * mechanism.
 *
 * **Client-confirmed (2026-09-04):** internal comments are disabled for
 * Podcast — comment discussion happens on YouTube instead. Gated
 * server-side by config('features.podcast_comments_enabled'), not just a
 * hidden form, so a direct POST is rejected the same as a genuinely
 * unpublished episode. The independent 🙌 reaction
 * (PodcastEpisodeReactionController) stays enabled.
 */
class PodcastEpisodeReviewController extends Controller
{
    public function store(Request $request, PodcastEpisode $episode, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($episode->status === PodcastEpisodeStatus::Published, 404);
        abort_unless(config('features.podcast_comments_enabled'), 404);

        // A real visitor never sees or fills in this field — see the
        // honeypot markup in resources/views/podcast/show.blade.php.
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

        $submit->handle($episode, $user, $data['content']);

        return back()->with('review_status', 'Thank you — your comment has been submitted.');
    }
}
