<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gratitude Journal reminders move from Daily/Weekly/None to On/Off + 1–7
 * reminders per week (2026-11-24). This is data only, with no schema change.
 * The values live in the existing user_preferences.preferences JSON blob.
 *
 * Each row that has the legacy `gratitude_reminder_frequency` key but no
 * `gratitude_reminders_enabled` key yet gains:
 *   daily  → enabled true,  per_week 7
 *   weekly → enabled true,  per_week 1   (still Monday, as before)
 *   none   → enabled false, per_week 7   (the default if switched back On)
 *
 * Nothing is removed. The legacy key and the scheduler's
 * `gratitude_reminder_last_sent_at` history stay exactly as they are. A
 * member with no user_preferences row needs nothing: the application
 * already treats that as the existing default (On, 7 per week, i.e. Daily).
 * Rows that already have the new keys are skipped, so this can be re-run
 * safely. The application also reads an unmigrated legacy row the same way
 * (GratitudeReminderPreference::fromStored()), so nothing changes for a
 * member between the code deploy and this migration running.
 *
 * down() strips only the keys this feature added, leaving the legacy key
 * (which the new code keeps in sync) for the previous code to read.
 */
return new class extends Migration
{
    private const MAP = [
        'daily' => [true, 7],
        'weekly' => [true, 1],
        'none' => [false, 7],
    ];

    public function up(): void
    {
        DB::table('user_preferences')
            ->whereNotNull('preferences')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $preferences = json_decode((string) $row->preferences, true);

                    if (! is_array($preferences) || array_key_exists('gratitude_reminders_enabled', $preferences)) {
                        continue;
                    }

                    $legacy = $preferences['gratitude_reminder_frequency'] ?? null;

                    if (! is_string($legacy) || ! isset(self::MAP[$legacy])) {
                        continue;
                    }

                    [$enabled, $perWeek] = self::MAP[$legacy];

                    $preferences['gratitude_reminders_enabled'] = $enabled;
                    $preferences['gratitude_reminders_per_week'] = $perWeek;

                    DB::table('user_preferences')
                        ->where('id', $row->id)
                        ->update(['preferences' => json_encode($preferences)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('user_preferences')
            ->whereNotNull('preferences')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $preferences = json_decode((string) $row->preferences, true);

                    if (! is_array($preferences)) {
                        continue;
                    }

                    $stripped = array_diff_key($preferences, array_flip([
                        'gratitude_reminders_enabled',
                        'gratitude_reminders_per_week',
                        'gratitude_reminder_week',
                        'gratitude_reminder_week_count',
                    ]));

                    if ($stripped !== $preferences) {
                        DB::table('user_preferences')
                            ->where('id', $row->id)
                            ->update(['preferences' => json_encode($stripped)]);
                    }
                }
            });
    }
};
