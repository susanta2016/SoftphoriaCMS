<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Newsletter\Actions\Campaigns\CancelCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\DuplicateCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\PrepareCampaignContent;
use App\Modules\Newsletter\Actions\Campaigns\ScheduleCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\SendCampaignNowAction;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\CreateNewsletterCampaign;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ViewNewsletterCampaign;
use App\Modules\Newsletter\Jobs\StartCampaignJob;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Mail\NewsletterMailer;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Services\CampaignLifecycle;
use App\Modules\Newsletter\Services\NewsletterDeliveryService;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\RenderedEmail;
use App\Modules\Newsletter\Support\SuppressionList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Campaign creation, scheduling and the background send: recipient
 * resolution, snapshots, consent re-checks, retries and idempotency.
 */
class CampaignSendingTest extends TestCase
{
    use InteractsWithNewsletter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enable();
    }

    public function test_an_admin_creates_a_draft_campaign_through_the_wizard(): void
    {
        $group = $this->group();
        $excluded = $this->group('Product Updates');
        $template = $this->template();

        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterCampaign::class)
            ->fillForm([
                'name' => 'Spring launch',
                'subject' => 'Something new',
                'preheader' => 'Have a look',
                'from_name' => 'Example News',
                'from_email' => 'news@example.com',
                'newsletter_template_id' => $template->id,
                'content' => '<p>Big news.</p>',
                'included_groups' => [(string) $group->id],
                'excluded_groups' => [(string) $excluded->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = NewsletterCampaign::query()->sole();
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame('Asia/Kolkata', $campaign->timezone);
        $this->assertSame([$group->id], $campaign->includedGroups()->pluck('newsletter_groups.id')->all());
        $this->assertSame([$excluded->id], $campaign->excludedGroups()->pluck('newsletter_groups.id')->all());
        $this->assertNull($campaign->scheduled_at);
    }

    public function test_the_wizard_rejects_an_unverified_sender_domain(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterCampaign::class)
            ->fillForm([
                'name' => 'x',
                'subject' => 'x',
                'from_name' => 'x',
                'from_email' => 'someone@not-verified.test',
                'newsletter_template_id' => $this->template()->id,
                'content' => '<p>x</p>',
                'included_groups' => [(string) $this->group()->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['from_email']);

        $this->assertDatabaseCount('newsletter_campaigns', 0);
    }

    public function test_recipients_combine_groups_once_per_address_and_drop_ineligible_ones(): void
    {
        $general = $this->group('General');
        $aws = $this->group('AWS & Cloud');
        $vip = $this->group('Excluded VIPs');

        $this->subscriber('both@example.com', groups: [$general, $aws]);
        $this->subscriber('general@example.com', groups: [$general]);
        $this->subscriber('aws@example.com', groups: [$aws]);
        $this->subscriber('excluded@example.com', groups: [$general, $vip]);
        $this->subscriber('gone@example.com', ['status' => 'unsubscribed'], [$general]);
        $this->subscriber('pending@example.com', ['status' => 'pending'], [$general]);
        $this->subscriber('bounced@example.com', ['status' => 'bounced'], [$general]);
        $this->subscriber('invalid@example.com', ['status' => 'invalid'], [$general]);
        // Status says subscribed, but the address is on the suppression list.
        $listed = $this->subscriber('listed@example.com', groups: [$general]);
        app(SuppressionList::class)->suppress('listed@example.com', SuppressionReason::Admin);
        $listed->forceFill(['status' => 'subscribed'])->save();

        $resolver = app(RecipientResolver::class);
        $this->assertSame(3, $resolver->count([$general->id, $aws->id], [$vip->id]));

        $campaign = $this->campaign([$general, $aws], [$vip]);
        $this->assertSame(3, $resolver->snapshot($campaign));
        $this->assertEqualsCanonicalizing(
            ['both@example.com', 'general@example.com', 'aws@example.com'],
            $campaign->recipients()->pluck('email')->all(),
        );
    }

    public function test_adding_a_suppressed_subscriber_to_a_group_never_makes_them_a_recipient(): void
    {
        $group = $this->group();
        $subscriber = $this->subscriber('blocked@example.com');
        app(SuppressionList::class)->suppress('blocked@example.com', SuppressionReason::Complaint);

        $subscriber->groups()->attach($group);

        $this->assertSame(0, app(RecipientResolver::class)->count([$group->id]));
    }

    public function test_scheduling_stores_utc_and_snapshots_the_content(): void
    {
        Queue::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);

        $local = now('Asia/Kolkata')->addDay()->setTime(9, 30);
        app(ScheduleCampaignAction::class)->handle($campaign, $this->admin(), $local->format('Y-m-d H:i'), 'Asia/Kolkata');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertSame($local->copy()->utc()->format('Y-m-d H:i'), $campaign->scheduled_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('09:30', $campaign->scheduledAtLocal()->format('H:i'));
        $this->assertNotNull($campaign->content_snapshot_at);
        $this->assertStringContainsString('This month we launched something new.', $campaign->rendered_html);
        $this->assertSame(['included' => ['General Newsletter'], 'excluded' => []], $campaign->groups_snapshot);
        $this->assertTrue(AuditLog::query()->where('action', 'newsletter_campaign.scheduled')->exists());
        Queue::assertNothingPushed();
    }

    public function test_a_time_in_the_past_cannot_be_scheduled(): void
    {
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);

        $this->expectExceptionObject(NewsletterException::invalid('The scheduled time must be in the future.'));

        app(ScheduleCampaignAction::class)->handle($this->campaign([$group]), $this->admin(), now('Asia/Kolkata')->subHour()->format('Y-m-d H:i'), 'Asia/Kolkata');
    }

    public function test_a_campaign_with_no_eligible_recipients_cannot_be_scheduled(): void
    {
        $group = $this->group();
        $this->subscriber('gone@example.com', ['status' => 'unsubscribed'], [$group]);

        $this->expectException(NewsletterException::class);

        app(ScheduleCampaignAction::class)->handle($this->campaign([$group]), $this->admin(), now()->addDay()->format('Y-m-d H:i'), 'Asia/Kolkata');
    }

    public function test_the_schedule_action_on_the_campaign_page(): void
    {
        Queue::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);

        Livewire::actingAs($this->admin())
            ->test(ViewNewsletterCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('schedule', data: [
                'date' => now('Asia/Kolkata')->addDays(2)->toDateString(),
                'time' => '08:00',
                'timezone' => 'Asia/Kolkata',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(CampaignStatus::Scheduled, $campaign->fresh()->status);
    }

    public function test_editing_the_template_after_scheduling_does_not_change_the_campaign(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $template = $this->template();
        $campaign = $this->campaign([$group], attributes: ['template' => $template]);
        app(ScheduleCampaignAction::class)->handle($campaign, $this->admin(), now('Asia/Kolkata')->addHour()->format('Y-m-d H:i'), 'Asia/Kolkata');

        $template->update(['html_content' => '<html><body><p>COMPLETELY NEW LAYOUT</p>{{ footer }}</body></html>']);

        $this->travel(2)->hours();
        $this->artisan('newsletter:dispatch-due')->assertSuccessful();

        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => ! str_contains($mail->htmlBody, 'COMPLETELY NEW LAYOUT')
            && str_contains($mail->htmlBody, 'This month we launched something new.'));
    }

    public function test_due_scheduled_campaigns_are_sent_by_the_dispatcher_with_personalization(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('ada@example.com', ['first_name' => 'Ada'], [$group]);
        $this->subscriber('alan@example.com', ['first_name' => 'Alan'], [$group]);
        $campaign = $this->campaign([$group]);
        app(ScheduleCampaignAction::class)->handle($campaign, $this->admin(), now('Asia/Kolkata')->addHour()->format('Y-m-d H:i'), 'Asia/Kolkata');

        $this->artisan('newsletter:dispatch-due');
        Mail::assertNothingSent();

        $this->travel(61)->minutes();
        $this->artisan('newsletter:dispatch-due')->assertSuccessful();

        Mail::assertSent(NewsletterCampaignMail::class, 2);
        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo('ada@example.com')
            && $mail->subjectLine === 'Hello Ada'
            && str_contains($mail->htmlBody, 'Hello Ada,')
            && str_contains($mail->textBody, '/newsletter/unsubscribe/'));

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Sent, $campaign->status);
        $this->assertSame(2, $campaign->recipients_total);
        $this->assertSame(2, $campaign->recipients()->where('status', CampaignRecipientStatus::Sent->value)->count());
        $this->assertDatabaseCount('newsletter_delivery_logs', 2);
    }

    public function test_every_email_carries_one_click_unsubscribe_and_tracking_headers(): void
    {
        config(['newsletter.ses_configuration_set' => 'newsletter-events']);
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);

        app(SendCampaignNowAction::class)->handle($this->campaign([$group]), $this->admin());

        $recipient = NewsletterCampaignRecipient::query()->sole();

        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail) use ($recipient): bool {
            $this->assertSame(route('newsletter.unsubscribe', ['token' => $recipient->token]), $mail->oneClickUnsubscribeUrl);
            $this->assertSame("{$recipient->token}@example.com", $mail->messageIdentifier);
            $this->assertSame('nl_recipient='.$recipient->token, $mail->extraHeaders['X-SES-MESSAGE-TAGS']);
            $this->assertSame('newsletter-events', $mail->extraHeaders['X-SES-CONFIGURATION-SET']);
            $this->assertSame('news@example.com', $mail->fromAddress);

            return true;
        });
    }

    public function test_one_email_per_address_even_in_several_selected_groups(): void
    {
        Mail::fake();
        $a = $this->group('A');
        $b = $this->group('B');
        $this->subscriber('both@example.com', groups: [$a, $b]);

        app(SendCampaignNowAction::class)->handle($this->campaign([$a, $b]), $this->admin());

        Mail::assertSent(NewsletterCampaignMail::class, 1);
    }

    public function test_send_now_queues_the_work_instead_of_sending_in_the_request(): void
    {
        Queue::fake();
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);

        $campaign = app(SendCampaignNowAction::class)->handle($this->campaign([$group]), $this->admin());

        $this->assertSame(CampaignStatus::Queued, $campaign->status);
        Queue::assertPushed(StartCampaignJob::class, 1);
        Mail::assertNothingSent();
    }

    public function test_a_subscriber_who_unsubscribes_after_the_snapshot_is_skipped(): void
    {
        Mail::fake();
        $group = $this->group();
        $staying = $this->subscriber('stay@example.com', groups: [$group]);
        $leaving = $this->subscriber('leave@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);

        app(PrepareCampaignContent::class)->snapshot($campaign);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);

        // Unsubscribes between the snapshot and the send.
        app(SuppressionList::class)->suppress($leaving->email, SuppressionReason::Unsubscribed);

        $delivery = app(NewsletterDeliveryService::class);
        $snapshot = new RenderedEmail($campaign->rendered_html, $campaign->rendered_text);

        foreach ($campaign->recipients as $recipient) {
            $delivery->deliver($campaign, $recipient, $snapshot);
        }

        Mail::assertSent(NewsletterCampaignMail::class, 1);
        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo($staying->email));
        $this->assertSame(CampaignRecipientStatus::Skipped, $campaign->recipients()->where('email', 'leave@example.com')->sole()->status);
    }

    public function test_delivering_the_same_recipient_twice_sends_once(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        $recipient = NewsletterCampaignRecipient::query()->sole();
        $snapshot = new RenderedEmail('<p>x</p>', 'x');

        $first = app(NewsletterDeliveryService::class)->deliver($campaign, $recipient, $snapshot);
        $second = app(NewsletterDeliveryService::class)->deliver($campaign, $recipient, $snapshot);

        $this->assertSame(CampaignRecipientStatus::Sent, $first);
        $this->assertSame(CampaignRecipientStatus::Sent, $second);
        Mail::assertSent(NewsletterCampaignMail::class, 1);
    }

    public function test_overlapping_dispatcher_runs_queue_a_campaign_once(): void
    {
        Queue::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(ScheduleCampaignAction::class)->handle($campaign, $this->admin(), now('Asia/Kolkata')->addHour()->format('Y-m-d H:i'), 'Asia/Kolkata');
        $this->travel(2)->hours();

        // Two runs holding the same stale model, as overlapping processes would.
        $stale = NewsletterCampaign::query()->find($campaign->id);
        $lifecycle = app(CampaignLifecycle::class);

        $this->assertTrue($lifecycle->queueDue($campaign->fresh()));
        $this->assertFalse($lifecycle->queueDue($stale));

        Queue::assertPushed(StartCampaignJob::class, 1);
    }

    public function test_a_failed_send_is_retried_and_then_succeeds(): void
    {
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        $recipient = NewsletterCampaignRecipient::query()->sole();

        $mailer = $this->mock(NewsletterMailer::class);
        $mailer->shouldReceive('mailerName')->andReturn('smtp');
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('Connection to smtp.secret-host failed'));
        $mailer->shouldReceive('send')->once()->andReturn('ses-message-1');

        $delivery = app(NewsletterDeliveryService::class);
        $snapshot = new RenderedEmail('<p>x</p>', 'x');

        $this->assertSame(CampaignRecipientStatus::Pending, $delivery->deliver($campaign, $recipient, $snapshot));
        $this->assertSame(1, $recipient->fresh()->attempts);

        $this->assertSame(CampaignRecipientStatus::Sent, $delivery->deliver($campaign, $recipient->fresh(), $snapshot));

        $recipient->refresh();
        $this->assertSame('ses-message-1', $recipient->provider_message_id);
        $this->assertSame(2, $recipient->attempts);
        $this->assertSame(['failed', 'submitted'], $recipient->deliveryLogs()->orderBy('id')->pluck('status')->all());
    }

    public function test_a_recipient_fails_after_the_maximum_attempts_and_the_campaign_is_partially_sent(): void
    {
        $group = $this->group();
        $this->subscriber('good@example.com', groups: [$group]);
        $this->subscriber('bad@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);

        $mailer = $this->mock(NewsletterMailer::class);
        $mailer->shouldReceive('mailerName')->andReturn('smtp');
        $mailer->shouldReceive('send')->withArgs(fn (string $to): bool => $to === 'good@example.com')->andReturn('id-1');
        $mailer->shouldReceive('send')->withArgs(fn (string $to): bool => $to === 'bad@example.com')->andThrow(new RuntimeException('rejected'));

        app(PrepareCampaignContent::class)->snapshot($campaign);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        $delivery = app(NewsletterDeliveryService::class);
        $snapshot = new RenderedEmail($campaign->rendered_html, $campaign->rendered_text);

        foreach (range(1, 3) as $attempt) {
            foreach ($campaign->recipients()->where('status', 'pending')->get() as $recipient) {
                $delivery->deliver($campaign, $recipient, $snapshot);
            }
        }

        $this->assertSame(CampaignRecipientStatus::Failed, $campaign->recipients()->where('email', 'bad@example.com')->sole()->status);
        $this->assertTrue(app(CampaignLifecycle::class)->finalizeIfDone($campaign));
        $this->assertSame(CampaignStatus::PartiallySent, $campaign->fresh()->status);
    }

    public function test_a_scheduled_campaign_found_long_after_its_time_is_failed_not_sent(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(ScheduleCampaignAction::class)->handle($campaign, $this->admin(), now('Asia/Kolkata')->addHour()->format('Y-m-d H:i'), 'Asia/Kolkata');

        // The module was off for two days; turned back on now.
        $this->travel(2)->days();
        $this->artisan('newsletter:dispatch-due')->assertSuccessful();

        Mail::assertNothingSent();
        $campaign->refresh();
        $this->assertSame(CampaignStatus::Failed, $campaign->status);
        $this->assertStringContainsString('Duplicate the campaign', $campaign->status_reason);
    }

    public function test_a_stalled_sending_campaign_is_resumed_without_resending(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('done@example.com', groups: [$group]);
        $this->subscriber('stuck@example.com', groups: [$group]);
        $this->subscriber('waiting@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(PrepareCampaignContent::class)->snapshot($campaign);
        $campaign->forceFill(['status' => CampaignStatus::Sending, 'started_at' => now(), 'last_progress_at' => now(), 'send_chain' => 'old', 'recipients_snapshot_at' => now()])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        $campaign->recipients()->where('email', 'done@example.com')->update(['status' => 'sent', 'sent_at' => now()]);
        $campaign->recipients()->where('email', 'stuck@example.com')->update(['status' => 'sending', 'claimed_at' => now(), 'attempts' => 1]);

        // The worker died; 20 minutes pass with no progress.
        $this->travel(20)->minutes();
        $this->artisan('newsletter:dispatch-due')->assertSuccessful();

        Mail::assertSent(NewsletterCampaignMail::class, 1);
        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo('waiting@example.com'));
        $this->assertSame(CampaignRecipientStatus::Failed, $campaign->recipients()->where('email', 'stuck@example.com')->sole()->status);
        $this->assertSame(CampaignStatus::PartiallySent, $campaign->fresh()->status);
    }

    public function test_cancelling_stops_the_remaining_recipients(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $this->subscriber('b@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(PrepareCampaignContent::class)->snapshot($campaign);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);

        app(CancelCampaignAction::class)->handle($campaign, $this->admin());

        foreach ($campaign->recipients as $recipient) {
            app(NewsletterDeliveryService::class)->deliver($campaign, $recipient, new RenderedEmail('<p>x</p>', 'x'));
        }

        Mail::assertNothingSent();
        $this->assertSame(CampaignStatus::Cancelled, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->recipients()->where('status', 'cancelled')->count());
    }

    public function test_invalid_status_transitions_are_refused(): void
    {
        $group = $this->group();
        $campaign = $this->campaign([$group]);
        $campaign->forceFill(['status' => CampaignStatus::Sent])->save();

        $this->expectException(NewsletterException::class);

        app(CancelCampaignAction::class)->handle($campaign, $this->admin());
    }

    public function test_unscheduling_returns_to_draft_and_only_drafts_can_be_edited(): void
    {
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        $admin = $this->admin();
        app(ScheduleCampaignAction::class)->handle($campaign, $admin, now('Asia/Kolkata')->addDay()->format('Y-m-d H:i'), 'Asia/Kolkata');

        $this->actingAs($admin)->get("/admin/newsletter/campaigns/{$campaign->id}/edit")->assertForbidden();

        app(CancelCampaignAction::class)->unschedule($campaign, $admin);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertNull($campaign->rendered_html);
        $this->actingAs($admin)->get("/admin/newsletter/campaigns/{$campaign->id}/edit")->assertOk();
    }

    public function test_duplicating_creates_a_fresh_draft_with_the_same_selection(): void
    {
        Mail::fake();
        $group = $this->group();
        $excluded = $this->group('Excluded');
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group], [$excluded]);
        app(SendCampaignNowAction::class)->handle($campaign, $this->admin());

        $copy = app(DuplicateCampaignAction::class)->handle($campaign->fresh(), $this->admin());

        $this->assertSame(CampaignStatus::Draft, $copy->status);
        $this->assertSame('Copy of October update', $copy->name);
        $this->assertNull($copy->rendered_html);
        $this->assertSame(0, $copy->recipients()->count());
        $this->assertSame([$group->id], $copy->includedGroups()->pluck('newsletter_groups.id')->all());
        $this->assertSame([$excluded->id], $copy->excludedGroups()->pluck('newsletter_groups.id')->all());
    }

    public function test_a_non_admin_cannot_send_a_campaign(): void
    {
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $member = User::factory()->create(['status' => UserStatus::Active->value]);

        $this->expectException(NewsletterException::class);

        app(SendCampaignNowAction::class)->handle($this->campaign([$group]), $member);
    }

    public function test_the_campaign_page_shows_the_report_after_sending(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(SendCampaignNowAction::class)->handle($campaign, $this->admin());

        $this->actingAs($this->admin())
            ->get("/admin/newsletter/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertSee('Delivery report')
            ->assertSee('Sent (submitted)');

        $this->actingAs($this->admin())->get('/admin/newsletter/campaigns/reports')->assertOk()->assertSee('October update');
    }
}
