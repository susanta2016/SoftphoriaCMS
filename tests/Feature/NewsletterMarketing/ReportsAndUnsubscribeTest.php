<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Actions\HandleSesCampaignEventAction;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterEvent;
use App\Modules\Newsletter\Support\CampaignReport;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\SuppressionList;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Post-send reporting (SES events, own open/click tracking), the
 * unsubscribe link and one-click unsubscribe, and their security.
 */
class ReportsAndUnsubscribeTest extends TestCase
{
    use InteractsWithNewsletter;
    use RefreshDatabase;

    private NewsletterCampaign $campaign;

    private NewsletterCampaignRecipient $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enable();

        $group = $this->group();
        $this->subscriber('reader@example.com', groups: [$group]);
        $this->campaign = $this->campaign([$group], attributes: [
            'track_opens' => true,
            'track_clicks' => true,
            'tracked_links' => ['0123456789abcdef' => 'https://example.com/launch'],
        ]);
        $this->campaign->forceFill(['status' => CampaignStatus::Sent, 'recipients_snapshot_at' => now()])->save();
        app(RecipientResolver::class)->snapshot($this->campaign);

        $this->recipient = NewsletterCampaignRecipient::query()->sole();
        $this->recipient->forceFill([
            'status' => CampaignRecipientStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => 'ses-0100-abc',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function sesEvent(string $type, array $extra = [], bool $byTag = true): array
    {
        return [
            'eventType' => $type,
            'mail' => [
                'messageId' => 'ses-0100-abc',
                'timestamp' => '2026-10-30T10:00:00.000Z',
                ...($byTag ? ['tags' => ['nl_recipient' => [$this->recipient->token]]] : []),
            ],
            ...$extra,
        ];
    }

    public function test_a_delivery_event_marks_the_recipient_delivered(): void
    {
        app(HandleSesCampaignEventAction::class)->handle($this->sesEvent('Delivery', ['delivery' => ['timestamp' => '2026-10-30T10:00:05.000Z']]));

        $this->recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Delivered, $this->recipient->status);
        $this->assertSame('2026-10-30 10:00:05', $this->recipient->delivered_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, CampaignReport::for($this->campaign)->get('delivered'));
    }

    public function test_events_are_matched_by_provider_message_id_without_a_tag(): void
    {
        app(HandleSesCampaignEventAction::class)->handle($this->sesEvent('Delivery', ['delivery' => ['timestamp' => '2026-10-30T10:00:05.000Z']], byTag: false));

        $this->assertSame(CampaignRecipientStatus::Delivered, $this->recipient->fresh()->status);
    }

    public function test_a_duplicate_webhook_event_is_counted_once(): void
    {
        $event = $this->sesEvent('Open', ['open' => ['timestamp' => '2026-10-30T11:00:00.000Z']]);

        app(HandleSesCampaignEventAction::class)->handle($event);
        app(HandleSesCampaignEventAction::class)->handle($event);

        $this->assertSame(1, $this->recipient->fresh()->open_count);
        $this->assertSame(1, NewsletterEvent::query()->count());
    }

    public function test_a_late_delivery_event_does_not_overwrite_a_bounce(): void
    {
        $handler = app(HandleSesCampaignEventAction::class);
        $handler->handle($this->sesEvent('Bounce', ['bounce' => ['bounceType' => 'Permanent', 'bounceSubType' => 'General', 'timestamp' => '2026-10-30T10:00:03.000Z', 'bouncedRecipients' => [['emailAddress' => 'reader@example.com']]]]));
        $handler->handle($this->sesEvent('Delivery', ['delivery' => ['timestamp' => '2026-10-30T10:00:09.000Z']]));

        $this->recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Bounced, $this->recipient->status);
        $report = CampaignReport::for($this->campaign);
        $this->assertSame(1, $report->get('bounced'));
        $this->assertSame(0, $report->get('failed'));
    }

    public function test_a_soft_bounce_is_recorded_without_changing_status(): void
    {
        app(HandleSesCampaignEventAction::class)->handle($this->sesEvent('Bounce', ['bounce' => ['bounceType' => 'Transient', 'timestamp' => '2026-10-30T10:00:03.000Z']]));

        $this->assertSame(CampaignRecipientStatus::Sent, $this->recipient->fresh()->status);
        $this->assertSame('soft_bounce', NewsletterEvent::query()->sole()->type->value);
    }

    public function test_complaints_and_clicks_are_tracked(): void
    {
        $handler = app(HandleSesCampaignEventAction::class);
        $handler->handle($this->sesEvent('Click', ['click' => ['timestamp' => '2026-10-30T12:00:00.000Z', 'link' => 'https://example.com/launch']]));
        $handler->handle($this->sesEvent('Complaint', ['complaint' => ['timestamp' => '2026-10-30T13:00:00.000Z', 'complaintFeedbackType' => 'abuse', 'complainedRecipients' => [['emailAddress' => 'reader@example.com']]]]));

        $report = CampaignReport::for($this->campaign);
        $this->assertSame(1, $report->get('clicked'));
        $this->assertSame(1, $report->get('opened'), 'A click implies an open.');
        $this->assertSame(1, $report->get('complained'));
    }

    public function test_the_ses_webhook_records_campaign_events_and_suppresses_on_complaint(): void
    {
        [$privateKey, $certPem] = $this->keyPair();
        $this->app->instance(MessageValidator::class, new MessageValidator(certClient: fn (string $url): string => $certPem));

        $sesEvent = $this->sesEvent('Complaint', ['complaint' => ['timestamp' => '2026-10-30T13:00:00.000Z', 'complaintFeedbackType' => 'abuse', 'complainedRecipients' => [['emailAddress' => 'reader@example.com']]]]);
        $data = [
            'Type' => 'Notification',
            'MessageId' => 'sns-1',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses-events',
            'Message' => json_encode($sesEvent),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem',
        ];
        openssl_sign((new MessageValidator)->getStringToSign(new Message([...$data, 'Signature' => 'x'])), $signature, $privateKey, OPENSSL_ALGO_SHA1);
        $data['Signature'] = base64_encode($signature);

        $this->call('POST', '/webhooks/ses', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data))->assertOk();
        // SNS redelivers the same message.
        $this->call('POST', '/webhooks/ses', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data))->assertOk();

        $this->assertSame(CampaignRecipientStatus::Complained, $this->recipient->fresh()->status);
        $this->assertSame(1, NewsletterEvent::query()->where('type', 'complaint')->count());
        $this->assertSame('complained', NewsletterSubscriber::query()->where('email', 'reader@example.com')->value('status'));
        $this->assertSame(SuppressionReason::Complaint, app(SuppressionList::class)->find('reader@example.com')->reason);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        $this->postJson('/webhooks/ses', ['Type' => 'Notification', 'Message' => json_encode($this->sesEvent('Delivery'))])->assertStatus(400);

        $this->assertSame(CampaignRecipientStatus::Sent, $this->recipient->fresh()->status);
    }

    public function test_the_open_pixel_records_an_open_once_a_minute(): void
    {
        $this->get("/n/o/{$this->recipient->token}")->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get("/n/o/{$this->recipient->token}")->assertOk();

        $this->assertSame(1, $this->recipient->fresh()->open_count);
        $this->assertNotNull($this->recipient->fresh()->opened_at);
    }

    public function test_the_open_pixel_records_nothing_when_tracking_is_off_or_the_module_is_disabled(): void
    {
        config(['newsletter.enabled' => false]);
        $this->get("/n/o/{$this->recipient->token}")->assertOk();

        $this->assertNull($this->recipient->fresh()->opened_at);
    }

    public function test_the_click_redirect_goes_only_to_the_campaigns_own_links(): void
    {
        $this->get("/n/c/{$this->recipient->token}/0123456789abcdef")->assertRedirect('https://example.com/launch');
        $this->assertSame(1, $this->recipient->fresh()->click_count);

        // An unknown hash, or a URL smuggled in the query, goes nowhere.
        $this->get("/n/c/{$this->recipient->token}/fedcba9876543210")->assertNotFound();
        $this->get("/n/c/{$this->recipient->token}/0123456789abcdef?url=https://evil.test")->assertRedirect('https://example.com/launch');
        $this->get('/n/c/'.str_repeat('z', 48).'/0123456789abcdef')->assertNotFound();
    }

    public function test_clicks_keep_redirecting_but_are_not_recorded_when_disabled(): void
    {
        config(['newsletter.enabled' => false]);

        $this->get("/n/c/{$this->recipient->token}/0123456789abcdef")->assertRedirect('https://example.com/launch');
        $this->assertSame(0, $this->recipient->fresh()->click_count);
    }

    public function test_opening_the_unsubscribe_link_only_asks_for_confirmation(): void
    {
        $this->get("/newsletter/unsubscribe/{$this->recipient->token}")
            ->assertOk()
            ->assertSee('Unsubscribe from')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // Link scanners following the URL must not unsubscribe anyone.
        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'reader@example.com')->value('status'));
    }

    public function test_confirming_unsubscribes_immediately_and_is_idempotent(): void
    {
        $this->post("/newsletter/unsubscribe/{$this->recipient->token}")->assertOk()->assertSee('unsubscribed');
        $this->post("/newsletter/unsubscribe/{$this->recipient->token}")->assertOk();

        $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->sole();
        $this->assertSame('unsubscribed', $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame(SuppressionReason::Unsubscribed, app(SuppressionList::class)->find('reader@example.com')->reason);
        $this->assertNotNull($this->recipient->fresh()->unsubscribed_at);
        $this->assertSame(1, NewsletterEvent::query()->where('type', 'unsubscribe')->count());
        $this->assertSame(1, CampaignReport::for($this->campaign)->get('unsubscribed'));

        // And they are out of every future campaign.
        $this->assertSame(0, app(RecipientResolver::class)->countForCampaign($this->campaign));
    }

    public function test_one_click_unsubscribe_from_the_mail_client(): void
    {
        // Mail clients POST without a session or CSRF token.
        $this->post("/newsletter/unsubscribe/{$this->recipient->token}", ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', NewsletterSubscriber::query()->where('email', 'reader@example.com')->value('status'));
        $this->assertSame('one_click', NewsletterEvent::query()->where('type', 'unsubscribe')->value('source'));
    }

    public function test_an_unknown_unsubscribe_token_changes_nothing_and_reveals_nothing(): void
    {
        $this->get('/newsletter/unsubscribe/'.str_repeat('x', 48))->assertOk()->assertSee('isn’t valid', false)->assertDontSee('reader@example.com');
        $this->post('/newsletter/unsubscribe/'.str_repeat('x', 48))->assertOk();
        $this->post('/newsletter/unsubscribe/'.str_repeat('x', 48), ['List-Unsubscribe' => 'One-Click'])->assertNotFound();

        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'reader@example.com')->value('status'));
    }

    public function test_unsubscribe_tokens_are_random_and_never_the_id_or_address(): void
    {
        $group = $this->group('Second');
        $this->subscriber('second@example.com', groups: [$group]);
        $other = $this->campaign([$group]);
        app(RecipientResolver::class)->snapshot($other);
        $otherToken = $other->recipients()->sole()->token;

        foreach ([$this->recipient->token, $otherToken] as $token) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
            $this->assertStringNotContainsString('example', $token);
        }

        $this->assertNotSame($this->recipient->token, $otherToken);
        $this->assertStringNotContainsString('reader@example.com', route('newsletter.unsubscribe', ['token' => $this->recipient->token]));
    }

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: string}
     */
    private function keyPair(): array
    {
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'sns.us-east-1.amazonaws.com'], $privateKey), null, $privateKey, 365);
        openssl_x509_export($cert, $certPem);

        return [$privateKey, $certPem];
    }
}
