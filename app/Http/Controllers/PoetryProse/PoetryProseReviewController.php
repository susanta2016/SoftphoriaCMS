<?php

namespace App\Http\Controllers\PoetryProse;

use App\Actions\Review\SubmitReviewAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing review/rating submission for a Poetry/Prose entry. Mirrors
 * PodcastEpisodeReviewController/TrackReviewController exactly: the `auth`
 * route middleware is the real server-side "guests cannot submit" gate (see
 * routes/web.php), and all persistence/moderation logic is delegated to the
 * same shared App\Actions\Review\SubmitReviewAction those modules use —
 * never a Poetry/Prose-specific copy.
 */
class PoetryProseReviewController extends Controller
{
    public function store(Request $request, PoetryProse $poetryProse, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($poetryProse->status === PoetryProseStatus::Published, 404);

        // Trimmed before validation so a whitespace-only submission (which
        // the JS guard in app.js can't catch if JavaScript is disabled)
        // fails the `required` rule below rather than being accepted as a
        // blank review.
        $request->merge(['content' => trim((string) $request->input('content'))]);

        $maxLength = config('reviews.max_length');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', "max:{$maxLength}"],
        ], [
            'rating.required' => 'Please select a rating from 1 to 5 stars.',
            'rating.between' => 'Please select a rating from 1 to 5 stars.',
            'content.required' => 'Please write a few words before submitting your review.',
            'content.max' => "Reviews can be at most {$maxLength} characters.",
        ]);

        /** @var User $user */
        $user = $request->user();

        $submit->handle($poetryProse, $user, (int) $data['rating'], $data['content']);

        return back()->with('review_status', 'Thank you — your review has been submitted.');
    }
}
