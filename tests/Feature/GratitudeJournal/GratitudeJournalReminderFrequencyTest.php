<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\UpdateGratitudeReminderFrequencyAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserPreference;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Support\Users\GratitudeReminderPreference;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Gratitude Journal reminders as On/Off + 1–7 reminders per week
 * (2026-11-24): the account page form, the deterministic weekly schedule
 * driven by a controlled clock across full Monday–Sunday weeks, the weekly
 * cap, prospective changes, and the Daily/Weekly/None data migration.
 *
 * The test week is 2023-01-02 (Monday) … 2023-01-08 (Sunday), ISO 2023-W01.
 */
class GratitudeJournalReminderFrequencyTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2023-01-02';

    private const MIGRATION = 'migrations/2026_11_24_100000_migrate_gratitude_reminder_frequency_to_per_week.php';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Preference page
    // ---------------------------------------------------------------

    public function test_the_preference_page_loads_with_the_default_on_and_seven_per_week(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.gratitude-journal.index'))
            ->assertOk()
            ->assertSee('Would you like to receive email reminders to write in your Gratitude Journal?')
            ->assertSee('How many reminders would you like to receive each week?')
            ->assertSee('name="gratitude_reminders_enabled" value="1"'."\n".'                                    checked', false)
            ->assertSee('class="mt-5 " data-gratitude-reminder-frequency', false)
            ->assertSee('<option value="7" selected>7 reminders per week (Daily)</option>', false)
            ->assertSee('1 reminder per week')
            ->assertSee('Save Preference')
            ->assertDontSee('name="gratitude_reminder_frequency"', false);
    }

    public function test_on_shows_the_frequency_selector(): void
    {
        $user = User::factory()->create();
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 3));

        $this->actingAs($user)->get(route('account.gratitude-journal.index'))
            ->assertOk()
            ->assertSee('class="mt-5 " data-gratitude-reminder-frequency', false)
            ->assertDontSee('class="mt-5 hidden" data-gratitude-reminder-frequency', false);
    }

    public function test_off_hides_the_frequency_selector(): void
    {
        $user = User::factory()->create();
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(false, 3));

        $this->actingAs($user)->get(route('account.gratitude-journal.index'))
            ->assertOk()
            ->assertSee('class="mt-5 hidden" data-gratitude-reminder-frequency', false)
            ->assertSee('name="gratitude_reminders_enabled" value="0"'."\n".'                                    checked', false)
            // The kept frequency is still pre-selected for when they switch On.
            ->assertSee('<option value="3" selected>', false);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function perWeekValues(): array
    {
        return collect(range(1, 7))->mapWithKeys(fn (int $n): array => ["{$n} per week" => [$n]])->all();
    }

    #[DataProvider('perWeekValues')]
    public function test_every_frequency_can_be_saved_and_is_shown_again_on_reload(int $perWeek): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.gratitude-journal.reminder'), [
                'gratitude_reminders_enabled' => '1',
                'gratitude_reminders_per_week' => (string) $perWeek,
            ])
            ->assertRedirect(route('account.gratitude-journal.index'))
            ->assertSessionHas('status', 'Your reminder preference has been updated.');

        $this->assertEquals(new GratitudeReminderPreference(true, $perWeek), $user->fresh()->gratitudeReminderPreference());

        $this->actingAs($user)->get(route('account.gratitude-journal.index'))
            ->assertOk()
            ->assertSee('<option value="'.$perWeek.'" selected>', false)
            ->assertSee('class="mt-5 " data-gratitude-reminder-frequency', false);
    }

    public function test_switching_off_keeps_the_saved_frequency_and_switching_on_restores_it(): void
    {
        $user = User::factory()->create();
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 4));

        // A hidden selector may or may not be submitted; neither loses the value.
        $this->actingAs($user)->put(route('account.gratitude-journal.reminder'), ['gratitude_reminders_enabled' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(new GratitudeReminderPreference(false, 4), $user->fresh()->gratitudeReminderPreference());

        $this->actingAs($user)->put(route('account.gratitude-journal.reminder'), [
            'gratitude_reminders_enabled' => '1',
            'gratitude_reminders_per_week' => '4',
        ]);
        $this->assertEquals(new GratitudeReminderPreference(true, 4), $user->fresh()->gratitudeReminderPreference());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidSubmissions(): array
    {
        return [
            'missing on/off' => [['gratitude_reminders_per_week' => '3'], 'gratitude_reminders_enabled'],
            'unknown on/off' => [['gratitude_reminders_enabled' => 'daily'], 'gratitude_reminders_enabled'],
            'on without a frequency' => [['gratitude_reminders_enabled' => '1'], 'gratitude_reminders_per_week'],
            'zero per week' => [['gratitude_reminders_enabled' => '1', 'gratitude_reminders_per_week' => '0'], 'gratitude_reminders_per_week'],
            'eight per week' => [['gratitude_reminders_enabled' => '1', 'gratitude_reminders_per_week' => '8'], 'gratitude_reminders_per_week'],
            'not a number' => [['gratitude_reminders_enabled' => '1', 'gratitude_reminders_per_week' => 'abc'], 'gratitude_reminders_per_week'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidSubmissions')]
    public function test_invalid_submissions_are_rejected_and_leave_the_preference_unchanged(array $payload, string $errorKey): void
    {
        $user = User::factory()->create();
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 2));

        $this->actingAs($user)->put(route('account.gratitude-journal.reminder'), $payload)
            ->assertRedirect(route('account.gratitude-journal.index'))
            ->assertSessionHasErrors($errorKey);

        $this->assertEquals(new GratitudeReminderPreference(true, 2), $user->fresh()->gratitudeReminderPreference());
    }

    public function test_saving_the_preference_does_not_touch_other_preference_keys(): void
    {
        $user = User::factory()->create();
        $preference = new UserPreference(['preferences' => ['theme' => 'dark', 'gratitude_reminder_last_sent_at' => '2023-01-01']]);
        $preference->user_id = $user->id;
        $preference->save();

        $this->actingAs($user)->put(route('account.gratitude-journal.reminder'), [
            'gratitude_reminders_enabled' => '1',
            'gratitude_reminders_per_week' => '5',
        ]);

        $stored = $user->fresh()->preferences->preferences;
        $this->assertSame('dark', $stored['theme']);
        $this->assertSame('2023-01-01', $stored['gratitude_reminder_last_sent_at']);
        $this->assertSame(5, $stored['gratitude_reminders_per_week']);
        $this->assertTrue($stored['gratitude_reminders_enabled']);
        // Rollback-safe legacy mirror (2–6 per week never exceed Weekly).
        $this->assertSame('weekly', $stored['gratitude_reminder_frequency']);
    }

    // ---------------------------------------------------------------
    // Scheduling across a full Monday–Sunday week
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{int, list<string>}>
     */
    public static function weeklySchedules(): array
    {
        return [
            '1 per week' => [1, ['Mon']],
            '2 per week' => [2, ['Mon', 'Thu']],
            '3 per week' => [3, ['Mon', 'Wed', 'Fri']],
            '4 per week' => [4, ['Mon', 'Wed', 'Fri', 'Sun']],
            '5 per week' => [5, ['Mon', 'Tue', 'Thu', 'Fri', 'Sat']],
            '6 per week' => [6, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']],
            '7 per week (daily)' => [7, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']],
        ];
    }

    /**
     * @param  list<string>  $expectedDays
     */
    #[DataProvider('weeklySchedules')]
    public function test_each_frequency_sends_exactly_that_many_reminders_on_its_fixed_days(int $perWeek, array $expectedDays): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, $perWeek));

        $sentOn = $this->runWeek(self::MONDAY, $user);

        $this->assertSame($expectedDays, $sentOn);
        $this->assertCount($perWeek, $sentOn);
    }

    #[DataProvider('weeklySchedules')]
    public function test_the_same_days_repeat_the_following_week(int $perWeek, array $expectedDays): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, $perWeek));

        $this->runWeek(self::MONDAY, $user);
        $secondWeek = $this->runWeek('2023-01-09', $user);

        $this->assertSame($expectedDays, $secondWeek);
    }

    public function test_running_the_scheduler_several_times_a_day_never_sends_duplicates(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $daily = $this->activeMember(new GratitudeReminderPreference(true, 7));
        $twice = $this->activeMember(new GratitudeReminderPreference(true, 2));

        Mail::fake();
        foreach (range(0, 6) as $offset) {
            foreach (['08:00:00', '08:00:30', '14:00:00', '23:59:00'] as $time) {
                Carbon::setTestNow(Carbon::parse(self::MONDAY)->addDays($offset)->setTimeFromTimeString($time));
                $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);
            }
        }

        $this->assertSame(7, $this->sentCountTo($daily));
        $this->assertSame(2, $this->sentCountTo($twice));
    }

    public function test_no_reminders_are_sent_while_reminders_are_off(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(false, 7));

        $this->assertSame([], $this->runWeek(self::MONDAY, $user));
    }

    public function test_inactive_members_still_receive_nothing(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Suspended->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 7));

        $this->assertSame([], $this->runWeek(self::MONDAY, $user));
    }

    public function test_missed_days_after_a_scheduler_outage_are_not_sent_in_bulk(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, 3)); // Mon, Wed, Fri

        // Scheduler down Monday–Thursday; first run back is Thursday.
        $sentOn = $this->runDays(self::MONDAY, $user, ['Thu', 'Fri', 'Sat', 'Sun']);

        $this->assertSame(['Fri'], $sentOn);
    }

    public function test_lowering_the_frequency_mid_week_never_exceeds_the_new_weekly_limit(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, 7));

        $firstDays = $this->runDays(self::MONDAY, $user, ['Mon', 'Tue', 'Wed']);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 3));
        $restOfWeek = $this->runDays(self::MONDAY, $user, ['Thu', 'Fri', 'Sat', 'Sun']);

        $this->assertSame(['Mon', 'Tue', 'Wed'], $firstDays);
        // 3 already sent this week, so Friday (a 3-per-week day) is skipped.
        $this->assertSame([], $restOfWeek);
        // From the next week onward, the new schedule applies in full.
        $this->assertSame(['Mon', 'Wed', 'Fri'], $this->runWeek('2023-01-09', $user));
    }

    public function test_raising_the_frequency_mid_week_applies_from_the_next_scheduled_day(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, 1));

        $monday = $this->runDays(self::MONDAY, $user, ['Mon', 'Tue']);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(true, 3));
        $rest = $this->runDays(self::MONDAY, $user, ['Wed', 'Thu', 'Fri', 'Sat', 'Sun']);

        $this->assertSame(['Mon'], $monday);
        $this->assertSame(['Wed', 'Fri'], $rest);
    }

    public function test_switching_off_stops_reminders_immediately_and_on_resumes_the_saved_frequency(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, 7));

        $this->assertSame(['Mon'], $this->runDays(self::MONDAY, $user, ['Mon']));

        (new UpdateGratitudeReminderFrequencyAction)->handle($user, new GratitudeReminderPreference(false, 7));
        $this->assertSame([], $this->runDays(self::MONDAY, $user, ['Tue', 'Wed', 'Thu']));

        $this->actingAs($user)->put(route('account.gratitude-journal.reminder'), [
            'gratitude_reminders_enabled' => '1',
            'gratitude_reminders_per_week' => (string) $user->fresh()->gratitudeReminderPreference()->perWeek,
        ]);
        $this->assertSame(['Fri', 'Sat', 'Sun'], $this->runDays(self::MONDAY, $user->fresh(), ['Fri', 'Sat', 'Sun']));
    }

    public function test_the_reminder_uses_the_existing_template_with_a_matching_frequency_label(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        Carbon::setTestNow(self::MONDAY.' 08:00:00');
        $daily = $this->activeMember(new GratitudeReminderPreference(true, 7));
        $weekly = $this->activeMember(new GratitudeReminderPreference(true, 1));
        $three = $this->activeMember(new GratitudeReminderPreference(true, 3));

        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        foreach ([[$daily, 'Daily'], [$weekly, 'Weekly'], [$three, 'Three-Times-Weekly']] as [$user, $label]) {
            Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email)
                && str_contains($mail->render(), "This is your {$label} reminder"));
        }
    }

    public function test_successful_sends_are_recorded_in_the_existing_history_keys(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        $user = $this->activeMember(new GratitudeReminderPreference(true, 2));

        Carbon::setTestNow('2023-01-05 08:00:00'); // Thursday
        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        $stored = $user->fresh()->preferences->preferences;
        $this->assertSame('2023-01-05', $stored['gratitude_reminder_last_sent_at']);
        $this->assertSame('2023-W01', $stored['gratitude_reminder_week']);
        $this->assertSame(1, $stored['gratitude_reminder_week_count']);
        $this->assertSame(2, $stored['gratitude_reminders_per_week']);
    }

    public function test_the_week_boundary_follows_iso_weeks_across_a_new_year(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = $this->activeMember(new GratitudeReminderPreference(true, 1));

        // 2024-12-30 is a Monday in ISO week 2025-W01, which also contains 2025-01-01.
        $this->assertSame(['Mon'], $this->runWeek('2024-12-30', $user));
        $this->assertSame('2025-W01', $user->fresh()->preferences->preferences['gratitude_reminder_week']);
    }

    public function test_the_delivery_time_is_unchanged_at_eight_am_in_the_application_timezone(): void
    {
        // The schedule is registered when Artisan starts (bootstrap/app.php withSchedule()).
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/0\s+8\s+\*\s+\*\s+\*\s+php artisan gratitude-journal:send-reminders/',
            Artisan::output(),
        );
        $this->assertSame('UTC', config('app.timezone'));
    }

    // ---------------------------------------------------------------
    // Backward compatibility / data migration
    // ---------------------------------------------------------------

    public function test_the_migration_maps_daily_weekly_and_none_and_keeps_history(): void
    {
        $daily = $this->legacyMember(['gratitude_reminder_frequency' => 'daily', 'gratitude_reminder_last_sent_at' => '2022-12-30']);
        $weekly = $this->legacyMember(['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminder_last_sent_at' => '2022-12-26', 'theme' => 'dark']);
        $none = $this->legacyMember(['gratitude_reminder_frequency' => 'none']);
        $unrelated = $this->legacyMember(['theme' => 'light']);
        $alreadyNew = $this->legacyMember(['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminders_enabled' => true, 'gratitude_reminders_per_week' => 4]);

        $this->runMigration('up');

        $this->assertStored($daily, ['gratitude_reminder_frequency' => 'daily', 'gratitude_reminder_last_sent_at' => '2022-12-30', 'gratitude_reminders_enabled' => true, 'gratitude_reminders_per_week' => 7]);
        $this->assertStored($weekly, ['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminder_last_sent_at' => '2022-12-26', 'theme' => 'dark', 'gratitude_reminders_enabled' => true, 'gratitude_reminders_per_week' => 1]);
        $this->assertStored($none, ['gratitude_reminder_frequency' => 'none', 'gratitude_reminders_enabled' => false, 'gratitude_reminders_per_week' => 7]);
        $this->assertStored($unrelated, ['theme' => 'light']);
        $this->assertStored($alreadyNew, ['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminders_enabled' => true, 'gratitude_reminders_per_week' => 4]);

        $this->assertEquals(new GratitudeReminderPreference(true, 7), $daily->fresh()->gratitudeReminderPreference());
        $this->assertEquals(new GratitudeReminderPreference(true, 1), $weekly->fresh()->gratitudeReminderPreference());
        $this->assertEquals(new GratitudeReminderPreference(false, 7), $none->fresh()->gratitudeReminderPreference());
        // No preferences row at all → the existing default (On, Daily).
        $this->assertEquals(new GratitudeReminderPreference(true, 7), User::factory()->create()->gratitudeReminderPreference());

        // Re-running is a no-op.
        $this->runMigration('up');
        $this->assertStored($weekly, ['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminder_last_sent_at' => '2022-12-26', 'theme' => 'dark', 'gratitude_reminders_enabled' => true, 'gratitude_reminders_per_week' => 1]);
    }

    public function test_migration_down_removes_only_the_new_keys(): void
    {
        $member = $this->legacyMember(['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminder_last_sent_at' => '2022-12-26']);
        $this->runMigration('up');

        $this->runMigration('down');

        $this->assertStored($member, ['gratitude_reminder_frequency' => 'weekly', 'gratitude_reminder_last_sent_at' => '2022-12-26']);
    }

    /**
     * A legacy row is read the same before and after the migration, so
     * nothing changes for a member between the code deploy and the migration.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function legacyValues(): array
    {
        return [
            'daily' => ['daily', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']],
            'weekly' => ['weekly', ['Mon']],
            'none' => ['none', []],
        ];
    }

    /**
     * @param  list<string>  $expectedDays
     */
    #[DataProvider('legacyValues')]
    public function test_legacy_members_get_the_same_reminders_whether_or_not_the_migration_has_run(string $legacy, array $expectedDays): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $unmigrated = $this->legacyMember(['gratitude_reminder_frequency' => $legacy]);
        $this->assertSame($expectedDays, $this->runWeek(self::MONDAY, $unmigrated));

        $migrated = $this->legacyMember(['gratitude_reminder_frequency' => $legacy]);
        $this->runMigration('up');
        $this->assertSame($expectedDays, $this->runWeek('2023-01-09', $migrated->fresh()));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function activeMember(GratitudeReminderPreference $reminder): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, $reminder);

        return $user;
    }

    /**
     * An Active member whose preferences blob is written raw, as an
     * existing pre-feature row would be.
     *
     * @param  array<string, mixed>  $preferences
     */
    private function legacyMember(array $preferences): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value]);

        DB::table('user_preferences')->insert([
            'user_id' => $user->id,
            'preferences' => json_encode($preferences),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function assertStored(User $user, array $expected): void
    {
        $actual = json_decode((string) DB::table('user_preferences')->where('user_id', $user->id)->value('preferences'), true);

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    private function runMigration(string $direction): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->{$direction}();
    }

    /**
     * Runs the scheduler at 08:00 on every day of the week starting at
     * $monday, and returns the days a reminder reached $user.
     *
     * @return list<string>
     */
    private function runWeek(string $monday, User $user): array
    {
        return $this->runDays($monday, $user, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    }

    /**
     * @param  list<string>  $days
     * @return list<string>
     */
    private function runDays(string $monday, User $user, array $days): array
    {
        $sentOn = [];

        foreach ($days as $day) {
            Mail::fake();
            Carbon::setTestNow(Carbon::parse($monday)->addDays(array_search($day, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], true))->setTime(8, 0));

            $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

            $count = $this->sentCountTo($user);
            $this->assertLessThanOrEqual(1, $count, "More than one reminder on {$day}.");

            if ($count === 1) {
                $sentOn[] = $day;
            }
        }

        return $sentOn;
    }

    private function sentCountTo(User $user): int
    {
        return Mail::sent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email))->count();
    }
}
