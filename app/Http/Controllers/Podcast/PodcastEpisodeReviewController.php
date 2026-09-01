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
 * Public-facing review/rating submission for a Podcast Episode — the `auth`
 * route middleware is the real server-side "guests cannot submit" gate (see
 * routes/web.php), matching the Download endpoint's pattern; hiding the
 * form for a guest in Blade is only a UI nicety on top of it. Delegates all
 * actual persistence/moderation logic to the shared, reusable
 * App\Actions\Review\SubmitReviewAction — Music and Inspirational Resources
 * will call the exact same action from their own future controllers.
 */
class PodcastEpisodeReviewController extends Controller
{
    public function store(Request $request, PodcastEpisode $episode, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($episode->status === PodcastEpisodeStatus::Published, 404);

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

        $submit->handle($episode, $user, (int) $data['rating'], $data['content']);

        return back()->with('review_status', 'Thank you — your review has been submitted.');
    }
}
