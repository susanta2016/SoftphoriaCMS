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
 * Public-facing review/rating submission for a Music Track — the single
 * submission endpoint for both a Single's listening page and an Album-owned
 * track's own page (both ultimately review the same underlying Track row;
 * see Track::reviewUrl()). Mirrors PodcastEpisodeReviewController exactly:
 * the `auth` route middleware is the real server-side "guests cannot
 * submit" gate (see routes/web.php), and all persistence/moderation logic
 * is delegated to the same shared App\Actions\Review\SubmitReviewAction
 * Podcast already uses — never a Music-specific copy.
 */
class TrackReviewController extends Controller
{
    public function store(Request $request, Track $track, SubmitReviewAction $submit): RedirectResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);

        $release = $track->release();
        abort_unless($release?->status === ReleaseStatus::Published, 404);

        // Trimmed before validation so a whitespace-only submission (which
        // the JS guard in app.js can't catch if JavaScript is disabled)
        // fails the `required` rule below rather than being accepted as a
        // blank review.
        $request->merge(['content' => trim((string) $request->input('content'))]);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', 'max:300'],
        ], [
            'rating.required' => 'Please select a rating from 1 to 5 stars.',
            'rating.between' => 'Please select a rating from 1 to 5 stars.',
            'content.required' => 'Please write a few words before submitting your review.',
            'content.max' => 'Reviews can be at most 300 characters.',
        ]);

        /** @var User $user */
        $user = $request->user();

        $submit->handle($track, $user, (int) $data['rating'], $data['content']);

        return back()->with('review_status', 'Thank you — your review has been submitted.');
    }
}
