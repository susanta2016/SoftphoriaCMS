<?php

namespace App\Console\Commands;

use App\Enums\EmailRecipientType;
use App\Enums\GratitudeReminderFrequency;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserPreference;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends the Gratitude Journal email reminder to every Active member whose
 * stored preference (User::gratitudeReminderFrequency(), read from the
 * existing UserPreference.preferences JSON blob — no new table/column) is
 * Daily, or Weekly on a Monday. A member who chose None is skipped
 * entirely. Registered against the Laravel scheduler in bootstrap/app.php
 * at a fixed 08:00 server time, following the exact same pattern as
 * PublishDuePagesCommand/PublishDuePodcastEpisodesCommand — no second
 * email-sending mechanism, this goes through the existing TemplatedMailer +
 * EmailTemplate registry (config/email_templates.php's
 * 'gratitude_journal_reminder' key) exactly like every other notification
 * in this codebase.
 *
 * Timezone (client-confirmed, 2026-09-04): uses config('app.timezone') —
 * the single application-wide timezone, via now()/today(), which Carbon
 * already resolves against that config — to decide what "today" and "is it
 * Monday" mean, the same for every member. Per-member delivery time or
 * per-member timezone scheduling is explicitly out of scope for this phase.
 *
 * Duplicate prevention: `gratitude_reminder_last_sent_at` (also stored in
 * the same preferences blob) records the date a reminder was last sent to
 * that member. A second run on the same day — a retried/duplicated
 * schedule tick — is a no-op for every member already marked sent, so this
 * command is safe to run more than once without double-emailing anyone.
 */
class SendGratitudeJournalRemindersCommand extends Command
{
    protected $signature = 'gratitude-journal:send-reminders';

    protected $description = 'Send the Gratitude Journal email reminder to members on their Daily/Weekly cadence';

    public function handle(TemplatedMailer $mailer): int
    {
        $sent = 0;
        $today = today();

        User::query()
            ->where('status', UserStatus::Active)
            ->with('preferences')
            ->chunkById(200, function ($users) use ($mailer, $today, &$sent): void {
                foreach ($users as $user) {
                    if ($this->sendIfDue($user, $mailer, $today)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} Gratitude Journal reminder(s).");

        return self::SUCCESS;
    }

    private function sendIfDue(User $user, TemplatedMailer $mailer, Carbon $today): bool
    {
        $frequency = $user->gratitudeReminderFrequency();

        if ($frequency === GratitudeReminderFrequency::None) {
            return false;
        }

        if ($frequency === GratitudeReminderFrequency::Weekly && ! $today->isMonday()) {
            return false;
        }

        $todayDate = $today->toDateString();
        $preferences = $user->preferences?->preferences ?? [];

        if (($preferences['gratitude_reminder_last_sent_at'] ?? null) === $todayDate) {
            return false;
        }

        $mailer->send('gratitude_journal_reminder', EmailRecipientType::User, $user->email, [
            'user_name' => $user->name,
            'journal_url' => route('account.gratitude-journal.index'),
            'frequency_label' => $frequency->getLabel(),
        ]);

        $this->markSent($user, $preferences, $todayDate);

        return true;
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function markSent(User $user, array $preferences, string $todayDate): void
    {
        $preference = $user->preferences ?? tap(new UserPreference, fn (UserPreference $new) => $new->user_id = $user->getKey());

        $preference->preferences = [
            ...$preferences,
            'gratitude_reminder_last_sent_at' => $todayDate,
        ];

        $preference->save();
    }
}
