<?php

namespace App\Actions\Review;

use App\Enums\EmailRecipientType;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Reviews\Reviewable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place a Review ever becomes publicly visible — called both by
 * the admin's Filament "Approve" action and, when
 * config('reviews.reviews_ratings_admin_approval') is false,
 * SubmitReviewAction immediately on submission. Either path sends the
 * submitter the same "review_published" TemplatedMailer email exactly once:
 * guarded by checking the status *before* this call, so re-saving an
 * already-approved review (an unrelated admin edit) never re-sends it.
 */
class PublishReviewAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(Review $review): Review
    {
        $alreadyApproved = $review->status === ReviewStatus::Approved;

        $review->status = ReviewStatus::Approved;
        $review->save();

        if (! $alreadyApproved) {
            $this->notify($review);
        }

        return $review;
    }

    private function notify(Review $review): void
    {
        $review->loadMissing(['user', 'reviewable']);

        $user = $review->user;
        $reviewable = $review->reviewable;

        if ($user === null || ! $reviewable instanceof Reviewable) {
            return;
        }

        try {
            $this->mailer->send('review_published', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'title' => $reviewable->reviewTitle(),
                'rating' => (string) $review->rating,
                'review_url' => $reviewable->reviewUrl(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Review published email failed to send', [
                'review_id' => $review->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
