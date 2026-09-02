<?php

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared submission entry point for any Reviewable model — Podcast Episode,
 * Music, and Poetry/Prose all via the same generic Review model rather than
 * a per-module copy.
 *
 * **Client-confirmed reversal (2026-09-02, two changes):**
 *
 * 1. The public form no longer collects a star rating — a submission here
 *    is now a plain text Light Post/comment. `rating` is always persisted
 *    as null; the column itself stays nullable (never dropped) so the
 *    small number of pre-existing rows with a real legacy rating remain
 *    intact and visible in the admin panel. See App\Models\Reaction for
 *    the separate, independent 🙌 reaction this replaces rating with.
 *
 * 2. **No longer one row per (reviewable, user).** A member may leave any
 *    number of comments on the same item over time — every call here
 *    always inserts a brand-new Review row via a plain `create()`, never
 *    updates an existing one. This reverses the original "second
 *    submission updates the first" behavior, which only made sense for a
 *    single star rating; `reviews`' old unique index on (reviewable_type,
 *    reviewable_id, user_id) was dropped in
 *    2026_09_02_130000_drop_reviews_unique_constraint.php specifically to
 *    allow this. Each row is independently moderated — one member's five
 *    comments on the same track can be five different Pending/Approved/
 *    Rejected states.
 */
class SubmitReviewAction
{
    public function __construct(private readonly PublishReviewAction $publish) {}

    public function handle(Model $reviewable, User $user, string $content): Review
    {
        $review = Review::query()->create([
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
            'user_id' => $user->getKey(),
            'rating' => null,
            'content' => $content,
            'status' => ReviewStatus::Pending,
        ]);

        if (! config('reviews.reviews_ratings_admin_approval')) {
            $this->publish->handle($review);
        }

        return $review->fresh();
    }
}
