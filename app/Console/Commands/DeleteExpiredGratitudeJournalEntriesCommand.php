<?php

namespace App\Console\Commands;

use App\Models\LightPost;
use Illuminate\Console\Command;

/**
 * Deletes Gratitude Journal entries (light_posts, source = journal — Public
 * and Private alike) older than config('features.gratitude_journal_retention_months'),
 * ENV-only (GRATITUDE_JOURNAL_RETENTION_MONTHS, default 6) per the client's
 * requirement: no Filament/admin setting, no database setting, no per-user
 * setting. Registered against the Laravel scheduler in bootstrap/app.php on
 * a daily cadence, the same pattern as PublishDuePagesCommand/
 * SendGratitudeJournalRemindersCommand.
 *
 * Scoped via LightPost::journal() — registration-time Light Posts
 * (source = registration) are structurally excluded from this query and
 * are never deleted by this command, regardless of age.
 *
 * Safe/idempotent: a plain `WHERE created_at < cutoff` delete is naturally
 * idempotent — re-running finds nothing left to delete for rows already
 * removed, and never touches a row that isn't yet past the cutoff. No
 * separate "already processed" marker is needed the way the reminder
 * command's send-once-per-day guard is, since deleting an already-deleted
 * row is simply a no-op.
 *
 * Calendar-month cutoff: subMonthsNoOverflow() (not subMonths()) — Carbon's
 * plain subMonths() overflows past a short month (e.g. Jan 31 minus 1 month
 * lands on Mar 3, not Feb 28), which would silently retain some entries
 * past their intended cutoff and be inconsistent month to month. The
 * "NoOverflow" variant clamps to the last valid day of the target month
 * instead, giving a stable, genuinely calendar-month cutoff.
 */
class DeleteExpiredGratitudeJournalEntriesCommand extends Command
{
    protected $signature = 'gratitude-journal:delete-expired';

    protected $description = 'Delete Gratitude Journal entries older than the configured retention period';

    public function handle(): int
    {
        $months = (int) config('features.gratitude_journal_retention_months');
        $cutoff = now()->subMonthsNoOverflow($months);

        $deleted = LightPost::query()
            ->journal()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} expired Gratitude Journal entry/entries.");

        return self::SUCCESS;
    }
}
