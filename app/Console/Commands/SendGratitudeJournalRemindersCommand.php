<?php

namespace App\Console\Commands;

use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserPreference;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Users\GratitudeReminderPreference;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends the Gratitude Journal email reminder to every Active member whose
 * reminders are On and whose chosen weekly frequency (1–7) is scheduled
 * for today. The preference is User::gratitudeReminderPreference(), read
 * from the existing UserPreference.preferences JSON blob, so there is no
 * new table or column. The fixed weekday set for each frequency is
 * GratitudeReminderPreference::SCHEDULE.
 * Registered against the Laravel scheduler in bootstrap/app.php at a fixed
 * 08:00 server time. Mail goes through the existing TemplatedMailer +
 * EmailTemplate registry ('gratitude_journal_reminder').
 *
 * Timezone (client-confirmed, 2026-09-04): config('app.timezone') decides
 * what "today" and its weekday mean, the same for every member. There is no
 * per-member delivery time or timezone.
 *
 * Safeguards, all kept in the same preferences blob:
 * - Duplicates: `gratitude_reminder_last_sent_at` (date). A second run on
 *   the same day is a no-op for every member already sent to.
 * - Weekly cap: `gratitude_reminder_week` (ISO week, e.g. 2026-W48) +
 *   `gratitude_reminder_week_count`. A member never gets more reminders in
 *   one Monday–Sunday week than their current frequency, even if they
 *   switch to a higher-day schedule mid-week.
 * - No catch-up: only today's weekday is ever checked, so days missed
 *   during an outage are simply skipped, never sent in bulk later.
 * - The preference is read fresh on every run, so a change (or switching
 *   Off) takes effect from the next run onward.
 */
class SendGratitudeJournalRemindersCommand extends Command
{
    public const KEY_LAST_SENT_AT = 'gratitude_reminder_last_sent_at';

    public const KEY_WEEK = 'gratitude_reminder_week';

    public const KEY_WEEK_COUNT = 'gratitude_reminder_week_count';

    protected $signature = 'gratitude-journal:send-reminders';

    protected $description = 'Send the Gratitude Journal email reminder to members whose chosen weekly frequency is scheduled today';

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
        $reminder = $user->gratitudeReminderPreference();

        if (! $reminder->isScheduledOn($today)) {
            return false;
        }

        $todayDate = $today->toDateString();
        $preferences = $user->preferences?->preferences ?? [];

        if (($preferences[self::KEY_LAST_SENT_AT] ?? null) === $todayDate) {
            return false;
        }

        $week = self::isoWeek($today);
        $sentThisWeek = ($preferences[self::KEY_WEEK] ?? null) === $week
            ? (int) ($preferences[self::KEY_WEEK_COUNT] ?? 0)
            : 0;

        if ($sentThisWeek >= $reminder->perWeek) {
            return false;
        }

        $mailer->send('gratitude_journal_reminder', EmailRecipientType::User, $user->email, [
            'user_name' => $user->name,
            'journal_url' => route('account.gratitude-journal.index'),
            'frequency_label' => $reminder->emailLabel(),
        ]);

        $this->markSent($user, $preferences, [
            self::KEY_LAST_SENT_AT => $todayDate,
            self::KEY_WEEK => $week,
            self::KEY_WEEK_COUNT => $sentThisWeek + 1,
        ]);

        return true;
    }

    public static function isoWeek(Carbon $day): string
    {
        return $day->format('o-\WW');
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @param  array<string, mixed>  $bookkeeping
     */
    private function markSent(User $user, array $preferences, array $bookkeeping): void
    {
        $preference = $user->preferences ?? tap(new UserPreference, fn (UserPreference $new) => $new->user_id = $user->getKey());

        $preference->preferences = [
            ...$preferences,
            ...$bookkeeping,
        ];

        $preference->save();
    }
}
