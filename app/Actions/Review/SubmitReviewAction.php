<?php

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared submission entry point for any Reviewable model — Podcast Episode
 * today, Music/Inspirational Resources later, all via the same generic
 * Review model rather than a per-module copy. One review per (reviewable,
 * user): a second submission from the same user updates their existing row
 * instead of creating a duplicate (reviews' own unique index is the
 * server-side backstop against a double-click/retry race doing both at
 * once). Editing an already-published review re-enters moderation exactly
 * like a first-time submission when admin approval is required — a user
 * cannot silently alter public content without re-review.
 */
class SubmitReviewAction
{
    public function __construct(private readonly PublishReviewAction $publish) {}

    public function handle(Model $reviewable, User $user, int $rating, string $content): Review
    {
        $review = Review::query()->updateOrCreate(
            [
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => $reviewable->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'rating' => $rating,
                'content' => $content,
                'status' => ReviewStatus::Pending,
            ],
        );

        if (! config('reviews.reviews_ratings_admin_approval')) {
            $this->publish->handle($review);
        }

        return $review->fresh();
    }
}
