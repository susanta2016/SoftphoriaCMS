<?php

namespace App\Actions\Review;

use App\Enums\EmailRecipientType;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Reviews\Reviewable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place a ReviewFlag row is ever created — called by each module's
 * own thin *ReviewFlagController (Poetry/Prose is the first consumer,
 * mirroring SubmitReviewAction/ToggleReactionAction's shared-action-per-thin-controller
 * shape). One flag per user per comment: a repeat click from the same user
 * is a silent no-op (the unique index on `review_flags` is the server-side
 * backstop against a double-click/retry race), so a comment is never
 * re-notified twice by the same reporter. Notifies both the comment's
 * author and every admin-role user, mirroring
 * App\Actions\Contact\SubmitContactFormAction's exact dual-recipient
 * pattern — each send independently try/caught so one bad address never
 * blocks the other recipient or turns a successful report into a 500.
 */
class FlagReviewAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @return bool true when a new flag was recorded, false when this user had already flagged it
     */
    public function handle(Review $review, User $reporter): bool
    {
        if ($review->flags()->where('user_id', $reporter->getKey())->exists()) {
            return false;
        }

        $review->flags()->create(['user_id' => $reporter->getKey()]);

        $this->notify($review, $reporter);

        return true;
    }

    private function notify(Review $review, User $reporter): void
    {
        $review->loadMissing(['user', 'reviewable']);

        $author = $review->user;
        $reviewable = $review->reviewable;

        $contentTitle = $reviewable instanceof Reviewable ? $reviewable->reviewTitle() : 'their comment';
        $contentUrl = $reviewable instanceof Reviewable ? $reviewable->reviewUrl() : null;

        $variables = [
            'commenter_name' => $author?->name ?? 'A member',
            'commenter_email' => $author?->email ?? '',
            'comment_content' => $review->content,
            'content_title' => $contentTitle,
            'content_url' => $contentUrl ?? '',
            'flagged_by_name' => $reporter->name,
            'flagged_by_email' => $reporter->email,
        ];

        if ($author !== null) {
            $this->notifyAuthor($review, $author, $variables);
        }

        $this->notifyAdmins($review, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAuthor(Review $review, User $author, array $variables): void
    {
        try {
            $this->mailer->send('review_comment_flagged', EmailRecipientType::User, $author->email, $variables);
        } catch (Throwable $exception) {
            Log::warning('Flagged comment author notification failed to send', [
                'review_id' => $review->getKey(),
                'user_id' => $author->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAdmins(Review $review, array $variables): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole === null) {
            return;
        }

        foreach ($adminRole->users as $admin) {
            try {
                $this->mailer->send('review_comment_flagged', EmailRecipientType::Admin, $admin->email, $variables);
            } catch (Throwable $exception) {
                Log::warning('Flagged comment admin notification failed to send', [
                    'review_id' => $review->getKey(),
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
