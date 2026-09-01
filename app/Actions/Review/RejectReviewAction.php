<?php

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Review;

/**
 * Keeps a Review out of public view without deleting it — the admin
 * moderation queue's "reject" counterpart to PublishReviewAction. Never
 * sends any email (only approval does).
 */
class RejectReviewAction
{
    public function handle(Review $review): Review
    {
        $review->status = ReviewStatus::Rejected;
        $review->save();

        return $review;
    }
}
