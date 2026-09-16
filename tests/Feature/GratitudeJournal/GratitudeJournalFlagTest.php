<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\LightPostFlag;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The 🚩 "report this entry" action on a Gratitude Journal shared-feed
 * entry (GratitudeJournalFlagController) — fully independent of
 * GratitudeJournalReactionTest's 🙌 coverage. App\Models\LightPostFlag is
 * a separate table/model, mirroring Tests\Feature\PoetryProse\
 * ReviewFlagTest's own reasoning for App\Models\ReviewFlag.
 */
class GratitudeJournalFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_report_an_entry_and_is_redirected_to_register(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();

        $response = $this->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertRedirect(route('register.show'));
        $this->assertSame(0, LightPostFlag::query()->count());
    }

    public function test_an_authenticated_member_can_report_an_entry(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertRedirect();
        $this->assertSame(1, LightPostFlag::query()->count());
        $flag = LightPostFlag::query()->first();
        $this->assertSame($reporter->getKey(), $flag->user_id);
        $this->assertSame($entry->getKey(), $flag->light_post_id);
    }

    public function test_the_async_endpoint_returns_an_appropriate_json_response(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->postJson(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertOk();
        $response->assertJson(['flagged' => true, 'already' => false]);
    }

    public function test_a_second_report_from_the_same_user_is_a_no_op(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->postJson(route('inspirational-resources.gratitude-journal.flags.store', $entry));
        $response = $this->actingAs($reporter)->postJson(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertOk();
        $response->assertJson(['flagged' => true, 'already' => true]);
        $this->assertSame(1, LightPostFlag::query()->count());
    }

    public function test_two_different_users_can_each_report_the_same_entry(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();
        $reporterA = User::factory()->create();
        $reporterB = User::factory()->create();

        $this->actingAs($reporterA)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));
        $this->actingAs($reporterB)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $this->assertSame(2, LightPostFlag::query()->count());
    }

    public function test_reporting_sends_the_entry_author_and_every_admin_an_email(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $this->seed(EmailTemplateSeeder::class);
        $author = User::factory()->create();
        $entry = $this->rawEntry($author);
        $reporter = User::factory()->create();
        $admin = $this->admin();
        Mail::fake();

        $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($author->email));
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($admin->email));
    }

    public function test_a_repeat_report_from_the_same_user_does_not_send_another_email(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $this->seed(EmailTemplateSeeder::class);
        $entry = $this->rawEntry();
        $reporter = User::factory()->create();
        $this->admin();
        Mail::fake();

        $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));
        Mail::assertSentCount(2); // author + admin, once each

        $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));
        Mail::assertSentCount(2); // unchanged — the second report was a no-op
    }

    public function test_a_private_journal_entry_cannot_be_reported(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry(overrideVisibility: GratitudeJournalVisibility::Private);
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertNotFound();
        $this->assertSame(0, LightPostFlag::query()->count());
    }

    /**
     * The key regression guard: LightPost is shared with registration-time
     * "Leave a Little Light" posts (source = registration) — this endpoint
     * must reject one outright, even though it shares the exact same model.
     */
    public function test_a_registration_light_post_cannot_be_reported(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $author = User::factory()->create();
        $registrationPost = LightPost::query()->create([
            'user_id' => $author->id,
            'source' => LightPostSource::Registration,
            'content' => 'A registration-time light post.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $registrationPost));

        $response->assertNotFound();
        $this->assertSame(0, LightPostFlag::query()->count());
    }

    public function test_reporting_is_disabled_and_the_endpoint_404s_when_the_config_is_off(): void
    {
        config(['features.gratitude_journal_flags_enabled' => false]);
        $entry = $this->publicEntry();
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('inspirational-resources.gratitude-journal.flags.store', $entry));

        $response->assertNotFound();
        $this->assertSame(0, LightPostFlag::query()->count());
    }

    public function test_the_feed_shows_a_reported_state_to_the_reporter(): void
    {
        config(['features.gratitude_journal_flags_enabled' => true]);
        $entry = $this->publicEntry();
        $reporter = User::factory()->create();
        LightPostFlag::query()->create(['light_post_id' => $entry->id, 'user_id' => $reporter->id]);

        $response = $this->actingAs($reporter)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('Reported');
    }

    public function test_the_feed_hides_the_report_control_when_disabled(): void
    {
        config(['features.gratitude_journal_flags_enabled' => false]);
        $this->publicEntry();
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertDontSee('data-flag-form', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function publicEntry(?User $author = null, ?GratitudeJournalVisibility $overrideVisibility = null): LightPost
    {
        $author ??= User::factory()->create();

        return app(CreateGratitudeJournalEntryAction::class)->handle(
            $author,
            'A public feed entry for report testing.',
            $overrideVisibility ?? GratitudeJournalVisibility::Public,
        );
    }

    /**
     * Built directly against the model rather than
     * CreateGratitudeJournalEntryAction, which itself sends a
     * "gratitude_journal_submitted" acknowledgement email — using the
     * action here would inflate Mail::assertSentCount() with an unrelated
     * send, exactly the reason Tests\Feature\PoetryProse\ReviewFlagTest
     * builds its Review rows directly rather than via SubmitReviewAction.
     */
    private function rawEntry(?User $author = null): LightPost
    {
        $author ??= User::factory()->create();

        return LightPost::query()->create([
            'user_id' => $author->id,
            'source' => LightPostSource::Journal,
            'content' => 'A public feed entry for report testing.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);
    }
}
