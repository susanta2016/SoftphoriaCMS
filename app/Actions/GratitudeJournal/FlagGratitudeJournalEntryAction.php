<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\EmailRecipientType;
use App\Models\LightPost;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place a LightPostFlag row is ever created — called by
 * GratitudeJournalFlagController, mirroring App\Actions\Review\
 * FlagReviewAction's exact shape for a Review comment. One flag per user
 * per entry: a repeat click from the same user is a silent no-op (the
 * unique index on `light_post_flags` is the server-side backstop against a
 * double-click/retry race), so an entry is never re-notified twice by the
 * same reporter. Notifies both the entry's author and every admin-role
 * user, same dual-recipient pattern as FlagReviewAction — each send
 * independently try/caught so one bad address never blocks the other
 * recipient or turns a successful report into a 500.
 */
class FlagGratitudeJournalEntryAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @return bool true when a new flag was recorded, false when this user had already flagged it
     */
    public function handle(LightPost $entry, User $reporter): bool
    {
        if ($entry->flags()->where('user_id', $reporter->getKey())->exists()) {
            return false;
        }

        $entry->flags()->create(['user_id' => $reporter->getKey()]);

        $this->notify($entry, $reporter);

        return true;
    }

    private function notify(LightPost $entry, User $reporter): void
    {
        $entry->loadMissing('user');

        $author = $entry->user;

        $variables = [
            'entry_author_name' => $author?->name ?? 'A member',
            'entry_author_email' => $author?->email ?? '',
            'entry_content' => $entry->content,
            'content_url' => route('inspirational-resources.gratitude-journal'),
            'flagged_by_name' => $reporter->name,
            'flagged_by_email' => $reporter->email,
        ];

        if ($author !== null) {
            $this->notifyAuthor($entry, $author, $variables);
        }

        $this->notifyAdmins($entry, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAuthor(LightPost $entry, User $author, array $variables): void
    {
        try {
            $this->mailer->send('gratitude_journal_entry_flagged', EmailRecipientType::User, $author->email, $variables);
        } catch (Throwable $exception) {
            Log::warning('Flagged Gratitude Journal entry author notification failed to send', [
                'light_post_id' => $entry->getKey(),
                'user_id' => $author->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAdmins(LightPost $entry, array $variables): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole === null) {
            return;
        }

        foreach ($adminRole->users as $admin) {
            try {
                $this->mailer->send('gratitude_journal_entry_flagged', EmailRecipientType::Admin, $admin->email, $variables);
            } catch (Throwable $exception) {
                Log::warning('Flagged Gratitude Journal entry admin notification failed to send', [
                    'light_post_id' => $entry->getKey(),
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
