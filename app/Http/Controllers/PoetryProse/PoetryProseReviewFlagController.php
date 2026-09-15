<?php

namespace App\Http\Controllers\PoetryProse;

use App\Actions\Review\FlagReviewAction;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-facing 🚩 "report this comment" for a Poetry/Prose entry comment —
 * independent of PoetryProseReactionController's 🙌 (a member can react,
 * comment, and/or report, all independently). The `auth` route middleware
 * is the real server-side "guests cannot report" gate (see routes/web.php),
 * same as the reaction/review routes. Dual-mode like
 * PoetryProseReactionController::toggle(): a `wantsJson()` request gets
 * `{flagged, already}` back; a plain form submission still gets the
 * original redirect-back, so the feature keeps working with JavaScript
 * disabled.
 */
class PoetryProseReviewFlagController extends Controller
{
    public function store(Request $request, PoetryProse $poetryProse, Review $review, FlagReviewAction $flag): JsonResponse|RedirectResponse
    {
        abort_unless($poetryProse->status === PoetryProseStatus::Published, 404);
        abort_unless(config('features.poetry_prose_comments_enabled'), 404);
        abort_unless(
            $review->reviewable_type === PoetryProse::class && $review->reviewable_id === $poetryProse->getKey(),
            404,
        );

        /** @var User $user */
        $user = $request->user();

        $created = $flag->handle($review, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'flagged' => true,
                'already' => ! $created,
            ]);
        }

        return back()->with('review_status', 'Thank you — this comment has been reported for review.');
    }
}
