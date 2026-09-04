<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\UpdateGratitudeReminderFrequencyAction;
use App\Enums\GratitudeReminderFrequency;
use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * SendGratitudeJournalRemindersCommand (Gratitude Journal audit §7/§8) —
 * reads each Active member's gratitude_reminder_frequency out of the
 * existing UserPreference.preferences JSON blob (no new table/column),
 * sends through the existing TemplatedMailer + EmailTemplate registry (the
 * same pattern as every other notification in this codebase, never a
 * second email mechanism), and never double-sends within the same local
 * calendar day.
 */
class GratitudeJournalReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_member_who_never_set_a_preference_defaults_to_daily(): void
    {
        $user = User::factory()->create();

        $this->assertSame(GratitudeReminderFrequency::Daily, $user->gratitudeReminderFrequency());
    }

    public function test_a_member_can_set_a_weekly_preference(): void
    {
        $user = User::factory()->create();

        (new UpdateGratitudeReminderFrequencyAction)->handle($user, GratitudeReminderFrequency::Weekly);

        $this->assertSame(GratitudeReminderFrequency::Weekly, $user->fresh()->gratitudeReminderFrequency());
    }

    public function test_a_daily_reminder_is_sent_to_a_member_with_the_default_preference(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Active->value]);

        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_a_weekly_reminder_is_sent_only_on_the_members_monday(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, GratitudeReminderFrequency::Weekly);

        // 2023-01-01 12:00 UTC is a Sunday — no reminder yet.
        Carbon::setTestNow('2023-01-01 12:00:00');
        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();

        // 2023-01-02 12:00 UTC is the following Monday — reminder sent.
        Carbon::setTestNow('2023-01-02 12:00:00');
        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_a_member_who_chose_none_receives_no_reminder(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($user, GratitudeReminderFrequency::None);

        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_running_the_command_twice_on_the_same_day_does_not_send_a_duplicate_reminder(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Active->value]);

        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);
        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        Mail::assertSent(TemplatedNotificationMail::class, 1);
    }

    /**
     * Client-confirmed (2026-09-04): reminder timing uses
     * config('app.timezone') uniformly for every member — no per-member
     * delivery time or per-member timezone scheduling in this phase (see
     * SendGratitudeJournalRemindersCommand's own docblock). This is the
     * regression guard for that scope boundary: two members in the same
     * application-wide "today" both receive the reminder together,
     * regardless of any per-user profile data.
     */
    public function test_reminder_timing_uses_the_application_timezone_for_every_member_alike(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        Carbon::setTestNow('2023-01-02 12:00:00'); // a Monday

        $userA = User::factory()->create(['status' => UserStatus::Active->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($userA, GratitudeReminderFrequency::Weekly);

        $userB = User::factory()->create(['status' => UserStatus::Active->value]);
        (new UpdateGratitudeReminderFrequencyAction)->handle($userB, GratitudeReminderFrequency::Weekly);

        $this->artisan('gratitude-journal:send-reminders')->assertExitCode(0);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($userA->email));
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($userB->email));
    }
}
