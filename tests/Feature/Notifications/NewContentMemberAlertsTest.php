<?php

namespace Tests\Feature\Notifications;

use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Jobs\Notifications\SendNewContentAlertJob;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\CreateResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\MarkResourceSubmissionInReviewAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\Music\Actions\Album\CreateAlbumAction;
use App\Modules\Music\Actions\Album\UpdateAlbumAction;
use App\Modules\Music\Actions\Single\CreateSingleAction;
use App\Modules\Music\Actions\Single\UpdateSingleAction;
use App\Modules\Music\Actions\Track\CreateTrackAction;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Podcast\Actions\PodcastEpisode\CreatePodcastEpisodeAction;
use App\Modules\Podcast\Actions\PodcastEpisode\UpdatePodcastEpisodeAction;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * All-member "new content" alerts for Music releases, Podcast episodes and
 * Inspirational Resources — sent once, on the first transition into the
 * member-visible state, to every Active user. See NewContentAlerter.
 */
class NewContentMemberAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const string MUSIC_SUBJECT = 'New Music:';

    private const string PODCAST_SUBJECT = 'New Podcast Episode:';

    private const string RESOURCE_SUBJECT = 'New Inspirational Resource:';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->admin = User::factory()->create(['status' => UserStatus::Active->value, 'email' => 'admin@example.com']);
        $this->admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        User::factory()->create(['status' => UserStatus::Active->value, 'email' => 'member-one@example.com']);
        User::factory()->create(['status' => UserStatus::Active->value, 'email' => 'member-two@example.com']);
        User::factory()->create(['status' => UserStatus::PendingVerification->value, 'email' => 'pending@example.com']);
        User::factory()->create(['status' => UserStatus::Suspended->value, 'email' => 'suspended@example.com']);
        User::factory()->create(['status' => UserStatus::Banned->value, 'email' => 'banned@example.com']);
    }

    // ---------------------------------------------------------------- Music

    public function test_publishing_a_draft_album_alerts_every_active_member(): void
    {
        $album = app(CreateAlbumAction::class)->handle(['title' => 'Here I Am', 'slug' => 'here-i-am'], $this->admin);
        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));

        app(UpdateAlbumAction::class)->handle($album, ['status' => ReleaseStatus::Published->value], $this->admin);

        $this->assertAlertRecipients(self::MUSIC_SUBJECT);
    }

    public function test_creating_a_single_directly_as_published_alerts_every_active_member(): void
    {
        app(CreateSingleAction::class)->handle([
            'title' => 'Still Water',
            'slug' => 'still-water',
            'status' => ReleaseStatus::Published->value,
        ], $this->admin);

        $this->assertAlertRecipients(self::MUSIC_SUBJECT);
    }

    public function test_the_scheduler_publishing_a_due_release_alerts_members(): void
    {
        app(CreateAlbumAction::class)->handle([
            'title' => 'Coming Soon',
            'slug' => 'coming-soon',
            'status' => ReleaseStatus::Scheduled->value,
            'publish_at' => now()->subMinute(),
        ], $this->admin);
        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));

        $this->artisan('music:publish-due-releases')->assertSuccessful();

        $this->assertAlertRecipients(self::MUSIC_SUBJECT);
    }

    public function test_draft_scheduled_and_archived_music_sends_nothing(): void
    {
        app(CreateAlbumAction::class)->handle(['title' => 'Draft', 'slug' => 'draft', 'status' => ReleaseStatus::Draft->value], $this->admin);
        app(CreateAlbumAction::class)->handle(['title' => 'Later', 'slug' => 'later', 'status' => ReleaseStatus::Scheduled->value, 'publish_at' => now()->addDay()], $this->admin);
        app(CreateSingleAction::class)->handle(['title' => 'Retired', 'slug' => 'retired', 'status' => ReleaseStatus::Archived->value], $this->admin);

        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));
    }

    public function test_editing_a_published_release_sends_nothing(): void
    {
        $single = app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);
        $sent = $this->alerts(self::MUSIC_SUBJECT)->count();

        app(UpdateSingleAction::class)->handle($single, ['title' => 'Still Water (Remastered)', 'description' => 'New notes.'], $this->admin);
        app(UpdateSingleAction::class)->handle($single, ['status' => ReleaseStatus::Published->value], $this->admin);

        $this->assertCount($sent, $this->alerts(self::MUSIC_SUBJECT));
    }

    public function test_archiving_and_republishing_a_release_does_not_alert_twice(): void
    {
        $album = app(CreateAlbumAction::class)->handle(['title' => 'Here I Am', 'slug' => 'here-i-am', 'status' => ReleaseStatus::Published->value], $this->admin);
        $sent = $this->alerts(self::MUSIC_SUBJECT)->count();

        app(UpdateAlbumAction::class)->handle($album, ['status' => ReleaseStatus::Archived->value], $this->admin);
        app(UpdateAlbumAction::class)->handle($album, ['status' => ReleaseStatus::Published->value], $this->admin);
        $album->save();

        $this->assertCount($sent, $this->alerts(self::MUSIC_SUBJECT));
        $this->assertDatabaseCount('new_content_alerts', 1);
    }

    public function test_publishing_a_track_on_an_already_published_album_sends_nothing(): void
    {
        $album = Album::withoutEvents(fn (): Album => Album::query()->create(['title' => 'Here I Am', 'slug' => 'here-i-am', 'status' => ReleaseStatus::Published]));

        app(CreateTrackAction::class)->handle([
            'album_id' => $album->id,
            'title' => 'Track One',
            'slug' => 'track-one',
            'status' => TrackStatus::Published->value,
        ], $this->admin);

        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));
    }

    // -------------------------------------------------------------- Podcast

    public function test_publishing_a_draft_episode_alerts_every_active_member(): void
    {
        $episode = app(CreatePodcastEpisodeAction::class)->handle($this->episodeData(), $this->admin);
        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));

        app(UpdatePodcastEpisodeAction::class)->handle($episode, ['status' => PodcastEpisodeStatus::Published->value], $this->admin);

        $this->assertAlertRecipients(self::PODCAST_SUBJECT);
    }

    public function test_the_scheduler_publishing_a_due_episode_alerts_members(): void
    {
        app(CreatePodcastEpisodeAction::class)->handle($this->episodeData([
            'status' => PodcastEpisodeStatus::Scheduled->value,
            'publish_at' => now()->subMinute(),
        ]), $this->admin);

        $this->artisan('podcast:publish-due-episodes')->assertSuccessful();

        $this->assertAlertRecipients(self::PODCAST_SUBJECT);
    }

    public function test_draft_or_scheduled_episodes_send_nothing(): void
    {
        app(CreatePodcastEpisodeAction::class)->handle($this->episodeData(), $this->admin);
        app(CreatePodcastEpisodeAction::class)->handle($this->episodeData([
            'slug' => 'later',
            'status' => PodcastEpisodeStatus::Scheduled->value,
            'publish_at' => now()->addDay(),
        ]), $this->admin);

        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));
    }

    public function test_a_published_episode_on_an_unpublished_show_sends_nothing(): void
    {
        app(CreatePodcastEpisodeAction::class)->handle($this->episodeData([
            'podcast_id' => $this->podcast(PodcastStatus::Draft)->id,
            'status' => PodcastEpisodeStatus::Published->value,
        ]), $this->admin);

        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));
    }

    public function test_editing_a_published_episode_sends_nothing_and_republishing_does_not_alert_twice(): void
    {
        $episode = app(CreatePodcastEpisodeAction::class)->handle($this->episodeData(['status' => PodcastEpisodeStatus::Published->value]), $this->admin);
        $sent = $this->alerts(self::PODCAST_SUBJECT)->count();
        $this->assertGreaterThan(0, $sent);

        app(UpdatePodcastEpisodeAction::class)->handle($episode, ['title' => 'Renamed', 'season' => 2], $this->admin);
        app(UpdatePodcastEpisodeAction::class)->handle($episode, ['status' => PodcastEpisodeStatus::Archived->value], $this->admin);
        app(UpdatePodcastEpisodeAction::class)->handle($episode, ['status' => PodcastEpisodeStatus::Published->value], $this->admin);

        $this->assertCount($sent, $this->alerts(self::PODCAST_SUBJECT));
    }

    // ------------------------------------------------------------- Resource

    public function test_a_public_submission_and_in_review_never_alert_members(): void
    {
        $submission = app(CreateResourceSubmissionAction::class)->handle([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Books',
            'message' => 'A story worth sharing.',
        ], null);

        app(MarkResourceSubmissionInReviewAction::class)->handle($submission->fresh(), $this->admin);

        $this->assertCount(0, $this->alerts(self::RESOURCE_SUBJECT));
    }

    public function test_approving_a_resource_alerts_every_active_member(): void
    {
        $submission = $this->submission();

        app(ApproveResourceSubmissionAction::class)->handle($submission, $this->admin);

        $this->assertAlertRecipients(self::RESOURCE_SUBJECT);
        // The submitter's own "your submission was published" email is unchanged.
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
            && str_contains($mail->subjectLine, 'Your Submission Has Been Published'));
    }

    public function test_a_submitter_who_is_not_an_active_member_gets_no_member_alert(): void
    {
        app(ApproveResourceSubmissionAction::class)->handle($this->submission(), $this->admin);

        $this->assertFalse($this->alerts(self::RESOURCE_SUBJECT)->contains(fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')));
    }

    public function test_editing_or_re_approving_a_published_resource_does_not_alert_twice(): void
    {
        $submission = $this->submission();
        app(ApproveResourceSubmissionAction::class)->handle($submission, $this->admin);
        $sent = $this->alerts(self::RESOURCE_SUBJECT)->count();

        $submission->message = 'Edited message.';
        $submission->save();
        app(ApproveResourceSubmissionAction::class)->handle($submission, $this->admin);

        $this->assertCount($sent, $this->alerts(self::RESOURCE_SUBJECT));
    }

    // ------------------------------------------------------ Email templates

    public function test_the_three_template_records_exist_as_enabled_user_templates(): void
    {
        foreach (['new_music_published', 'new_podcast_episode_published', 'new_inspirational_resource_published'] as $key) {
            $templates = EmailTemplate::query()->where('notification_key', $key)->get();

            $this->assertCount(1, $templates, $key);
            $this->assertSame(EmailRecipientType::User, $templates->first()->recipient_type, $key);
            $this->assertTrue($templates->first()->is_enabled, $key);
        }

        $this->assertSame('New Music Published', config('email_templates.new_music_published.label'));
        $this->assertSame('New Podcast Published', config('email_templates.new_podcast_episode_published.label'));
        $this->assertSame('New Inspirational Resource Published', config('email_templates.new_inspirational_resource_published.label'));
    }

    public function test_the_templates_are_listed_and_editable_in_admin(): void
    {
        $template = EmailTemplate::query()->where('notification_key', 'new_music_published')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(ListEmailTemplates::class)
            ->searchTable('new_music_published')
            ->assertCanSeeTableRecords([$template]);

        Livewire::actingAs($this->admin)
            ->test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm([
                'subject' => 'Fresh from the studio: {{release_title}}',
                'html_body' => '<p>Hello {{user_name}}, hear it at {{release_url}}</p>',
                'is_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();
        $this->assertSame('Fresh from the studio: {{release_title}}', $template->subject);
        $this->assertFalse($template->is_enabled);
    }

    public function test_a_disabled_template_sends_nothing_and_re_enabling_does_not_back_fill(): void
    {
        EmailTemplate::query()->where('notification_key', 'new_podcast_episode_published')->update(['is_enabled' => false]);

        $episode = app(CreatePodcastEpisodeAction::class)->handle($this->episodeData(['status' => PodcastEpisodeStatus::Published->value]), $this->admin);
        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));

        EmailTemplate::query()->where('notification_key', 'new_podcast_episode_published')->update(['is_enabled' => true]);
        app(UpdatePodcastEpisodeAction::class)->handle($episode, ['title' => 'Edited'], $this->admin);

        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));
    }

    public function test_disabling_one_template_leaves_the_others_sending(): void
    {
        EmailTemplate::query()->where('notification_key', 'new_music_published')->update(['is_enabled' => false]);

        app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);
        app(ApproveResourceSubmissionAction::class)->handle($this->submission(), $this->admin);

        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));
        $this->assertAlertRecipients(self::RESOURCE_SUBJECT);
    }

    public function test_tokens_are_substituted_and_the_shared_layout_is_used(): void
    {
        $single = app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);

        $mail = $this->alerts(self::MUSIC_SUBJECT)->first(fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('member-one@example.com'));
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Still Water', $mail->subjectLine);

        $html = $mail->render();
        $this->assertStringContainsString('Still Water', $html);
        $this->assertStringContainsString(route('music.singles.show', $single), $html);
        $this->assertStringContainsString('single', $html);
        $this->assertDoesNotMatchRegularExpression('/\{\{\s*[a-z_]+\s*\}\}/', $html);
        $this->assertStringContainsString('class="email-container"', $html);

        $direct = app(TemplatedMailer::class)->renderAsMailable('new_music_published', EmailRecipientType::User, [
            'user_name' => 'x', 'release_title' => 'x', 'release_type' => 'x', 'release_url' => 'x',
        ]);
        $this->assertSame(TemplatedNotificationMail::class, $direct::class);
    }

    public function test_resource_titles_from_the_public_form_are_stripped_of_markup(): void
    {
        app(ApproveResourceSubmissionAction::class)->handle($this->submission(['subject' => '<a href="https://evil.example">Click</a> me']), $this->admin);

        $html = $this->alerts(self::RESOURCE_SUBJECT)->first()->render();

        $this->assertStringContainsString('Click me', $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }

    // --------------------------------------------------- Delivery/resilience

    public function test_publishing_queues_exactly_one_alert_job(): void
    {
        Queue::fake();

        $album = app(CreateAlbumAction::class)->handle(['title' => 'Here I Am', 'slug' => 'here-i-am', 'status' => ReleaseStatus::Published->value], $this->admin);
        app(UpdateAlbumAction::class)->handle($album, ['title' => 'Here I Am (Deluxe)'], $this->admin);

        Queue::assertPushed(SendNewContentAlertJob::class, 1);
        Queue::assertPushed(SendNewContentAlertJob::class, fn (SendNewContentAlertJob $job): bool => $job->notificationKey === 'new_music_published'
            && $job->variables['release_title'] === 'Here I Am');
        $this->assertSame(1, (new SendNewContentAlertJob('k', []))->tries);
    }

    public function test_a_mail_failure_never_breaks_publication(): void
    {
        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('renderAsMailable')->andThrow(new \RuntimeException('SMTP unavailable'));
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(TemplatedMailer::class, $mailer);

        $single = app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);

        $this->assertSame(ReleaseStatus::Published, $single->fresh()->status);
    }

    public function test_one_failing_recipient_does_not_stop_the_rest(): void
    {
        $real = app(TemplatedMailer::class);
        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('renderAsMailable')->andReturnUsing(fn (...$args) => $real->renderAsMailable(...$args));
        $mailer->shouldReceive('send')->andReturnUsing(function (string $key, EmailRecipientType $type, string $to, array $variables) use ($real): void {
            if ($to === 'member-one@example.com') {
                throw new \RuntimeException('Mailbox unavailable');
            }
            $real->send($key, $type, $to, $variables);
        });
        $this->app->instance(TemplatedMailer::class, $mailer);

        app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);

        $recipients = $this->alerts(self::MUSIC_SUBJECT)->flatMap(fn (TemplatedNotificationMail $mail): array => array_column($mail->to, 'address'))->sort()->values()->all();
        $this->assertSame(['admin@example.com', 'member-two@example.com'], $recipients);
    }

    public function test_an_unroutable_resource_is_still_approved_and_the_failure_is_logged(): void
    {
        Log::spy();
        $submission = $this->submission(['slug' => null]);

        app(ApproveResourceSubmissionAction::class)->handle($submission, $this->admin);

        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->fresh()->status);
        $this->assertCount(0, $this->alerts(self::RESOURCE_SUBJECT));
        $this->assertPreparationFailureLogged();
    }

    public function test_unroutable_music_and_episodes_still_publish_and_the_failure_is_logged(): void
    {
        Log::spy();

        $album = app(CreateAlbumAction::class)->handle(['title' => 'No Slug Album', 'slug' => '', 'status' => ReleaseStatus::Published->value], $this->admin);
        $single = app(CreateSingleAction::class)->handle(['title' => 'No Slug Single', 'slug' => '', 'status' => ReleaseStatus::Published->value], $this->admin);
        $episode = app(CreatePodcastEpisodeAction::class)->handle($this->episodeData(['slug' => '', 'status' => PodcastEpisodeStatus::Published->value]), $this->admin);

        $this->assertSame(ReleaseStatus::Published, $album->fresh()->status);
        $this->assertSame(ReleaseStatus::Published, $single->fresh()->status);
        $this->assertSame(PodcastEpisodeStatus::Published, $episode->fresh()->status);
        $this->assertCount(0, $this->alerts(self::MUSIC_SUBJECT));
        $this->assertCount(0, $this->alerts(self::PODCAST_SUBJECT));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'New content member alert could not be prepared')->times(3);
    }

    public function test_a_database_error_while_claiming_the_alert_is_not_swallowed(): void
    {
        Schema::drop('new_content_alerts');

        $this->expectException(QueryException::class);

        app(CreateSingleAction::class)->handle(['title' => 'Still Water', 'slug' => 'still-water', 'status' => ReleaseStatus::Published->value], $this->admin);
    }

    // -------------------------------------------------------------- Helpers

    private function assertPreparationFailureLogged(): void
    {
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => $message === 'New content member alert could not be prepared'
            && str_contains($context['exception'] ?? '', 'UrlGenerationException'))->once();
    }

    /**
     * @return Collection<int, TemplatedNotificationMail>
     */
    private function alerts(string $subjectFragment): Collection
    {
        return Mail::sent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => str_contains($mail->subjectLine, $subjectFragment));
    }

    /**
     * Exactly one alert to each Active user (admin included), none to
     * pending/suspended/banned accounts or anyone else.
     */
    private function assertAlertRecipients(string $subjectFragment): void
    {
        $recipients = $this->alerts($subjectFragment)
            ->flatMap(fn (TemplatedNotificationMail $mail): array => array_column($mail->to, 'address'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['admin@example.com', 'member-one@example.com', 'member-two@example.com'], $recipients);
    }

    private function podcast(PodcastStatus $status = PodcastStatus::Published): Podcast
    {
        return Podcast::query()->create([
            'title' => 'All The Things Light Podcast',
            'slug' => 'all-the-things-light-podcast-'.uniqid(),
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function episodeData(array $overrides = []): array
    {
        return [
            'podcast_id' => $overrides['podcast_id'] ?? $this->podcast()->id,
            'title' => 'The Power of Presence',
            'slug' => 'the-power-of-presence',
            'status' => PodcastEpisodeStatus::Draft->value,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submission(array $overrides = []): ResourceSubmission
    {
        return ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Books',
            'message' => 'A story worth sharing.',
            'slug' => 'my-story',
            'status' => ResourceSubmissionStatus::Submitted,
            ...$overrides,
        ]);
    }
}
