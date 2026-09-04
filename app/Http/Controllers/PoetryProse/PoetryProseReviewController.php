<?php

namespace App\Http\Controllers\PoetryProse;

use App\Actions\Review\SubmitReviewAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Rules\MaxWords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing comment submission for a Poetry/Prose ("Light Posts" is
 * display text only — see resources/views for the label, this class/route/
 * table keep their existing naming) entry. Mirrors
 * PodcastEpisodeReviewController/TrackReviewController's shape: the `auth`
 * route middleware is the real server-side "guests cannot submit" gate (see
 * routes/web.php), and persistence/moderation is delegated to the same
 * shared App\Actions\Review\SubmitReviewAction those modules use — never a
 * Poetry/Prose-specific copy.
 *
 * **Client-confirmed reversal (2026-09-02):** no longer collects a star
 * rating — see SubmitReviewAction's own docblock. The honeypot check below
 * reuses ContactController::store()'s exact pattern (same field name,
 * same silent-success-on-trip behavior) rather than a new spam-prevention
 * mechanism.
 *
 * **Client-confirmed (2026-09-04):** unlike Music/Podcast, comments here are
 * gated by config('features.poetry_prose_comments_enabled') (server-side —
 * a disabled module must reject a submission even if the hidden form is
 * bypassed) and validated by WORD count via App\Rules\MaxWords against
 * config('features.poetry_prose_comment_max_words'), not the shared
 * character-based config('reviews.max_length') that Music/Podcast keep
 * using unchanged.
 */
class PoetryProseReviewController extends Controller
{
    public function store(Request $request, PoetryProse $poetryProse, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($poetryProse->status === PoetryProseStatus::Published, 404);
        abort_unless(config('features.poetry_prose_comments_enabled'), 404);

        // A real visitor never sees or fills in this field — see the
        // honeypot markup in resources/views/poetry-prose/show.blade.php.
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

        $maxWords = config('features.poetry_prose_comment_max_words');

        $data = $request->validate([
            'content' => ['required', 'string', new MaxWords($maxWords)],
        ], [
            'content.required' => 'Please write a few words before submitting your comment.',
        ]);

        /** @var User $user */
        $user = $request->user();

        $submit->handle($poetryProse, $user, $data['content']);

        return back()->with('review_status', 'Thank you — your comment has been submitted.');
    }
}
