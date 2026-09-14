<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /webhooks/ses (App\Http\Controllers\Newsletter\SesWebhookController).
 * Every "valid" message here is genuinely RSA-signed with a locally
 * generated test keypair and verified through the real
 * aws/aws-php-sns-message-validator MessageValidator (only its certificate
 * fetch is swapped for the test cert, via container binding) — this
 * exercises the actual signature-verification code path, not a stub of it.
 */
class SesWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{0: \OpenSSLAsymmetricKey, 1: string}|null */
    private static ?array $keyPair = null;

    protected function setUp(): void
    {
        parent::setUp();

        [, $certPem] = $this->keyPair();

        $this->app->instance(MessageValidator::class, new MessageValidator(
            certClient: fn (string $url): string => $certPem,
        ));
    }

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: string}
     */
    private function keyPair(): array
    {
        if (self::$keyPair !== null) {
            return self::$keyPair;
        }

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(['commonName' => 'sns.us-east-1.amazonaws.com'], $privateKey);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365);
        openssl_x509_export($cert, $certPem);

        return self::$keyPair = [$privateKey, $certPem];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedSnsMessage(array $overrides = []): array
    {
        [$privateKey] = $this->keyPair();

        $data = array_merge([
            'Type' => 'Notification',
            'MessageId' => 'msg-'.uniqid(),
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses-events',
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem',
            'Signature' => 'placeholder',
        ], $overrides);

        $stringToSign = (new MessageValidator)->getStringToSign(new Message($data));

        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        $data['Signature'] = base64_encode($signature);

        return $data;
    }

    private function postSnsMessage(array $data): TestResponse
    {
        return $this->call('POST', '/webhooks/ses', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
    }

    public function test_a_valid_sns_subscription_confirmation_is_handled(): void
    {
        Http::fake(['*' => Http::response('OK')]);

        $subscribeUrl = 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&Token=abc123';

        $data = $this->signedSnsMessage([
            'Type' => 'SubscriptionConfirmation',
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => $subscribeUrl,
            'Token' => 'abc123',
        ]);

        $response = $this->postSnsMessage($data);

        $response->assertOk();
        Http::assertSent(fn ($request): bool => $request->url() === $subscribeUrl);
    }

    public function test_an_invalid_sns_signature_is_rejected(): void
    {
        $data = $this->signedSnsMessage([
            'Message' => json_encode(['notificationType' => 'Bounce']),
        ]);
        $data['Signature'] = base64_encode('not-a-real-signature');

        $response = $this->postSnsMessage($data);

        $response->assertStatus(400);
    }

    public function test_a_valid_hard_bounce_marks_the_subscriber_bounced(): void
    {
        NewsletterSubscriber::query()->create(['email' => 'bounced@example.com', 'status' => 'subscribed', 'consented_at' => now()]);

        $data = $this->signedSnsMessage([
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'timestamp' => now()->toIso8601String(),
                    'bouncedRecipients' => [['emailAddress' => 'Bounced@Example.com']],
                ],
            ]),
        ]);

        $response = $this->postSnsMessage($data);

        $response->assertOk();

        $subscriber = NewsletterSubscriber::query()->where('email', 'bounced@example.com')->firstOrFail();
        $this->assertSame('bounced', $subscriber->status);
        $this->assertSame('bounce', $subscriber->ses_event_type);
        $this->assertNotNull($subscriber->ses_event_at);
    }

    public function test_a_transient_bounce_does_not_permanently_suppress_the_subscriber(): void
    {
        NewsletterSubscriber::query()->create(['email' => 'transient@example.com', 'status' => 'subscribed', 'consented_at' => now()]);

        $data = $this->signedSnsMessage([
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Transient',
                    'bounceSubType' => 'MailboxFull',
                    'bouncedRecipients' => [['emailAddress' => 'transient@example.com']],
                ],
            ]),
        ]);

        $this->postSnsMessage($data)->assertOk();

        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'transient@example.com')->value('status'));
    }

    public function test_a_valid_complaint_marks_the_subscriber_complained(): void
    {
        NewsletterSubscriber::query()->create(['email' => 'complainer@example.com', 'status' => 'subscribed', 'consented_at' => now()]);

        $data = $this->signedSnsMessage([
            'Message' => json_encode([
                'notificationType' => 'Complaint',
                'complaint' => [
                    'timestamp' => now()->toIso8601String(),
                    'complaintFeedbackType' => 'abuse',
                    'complainedRecipients' => [['emailAddress' => 'complainer@example.com']],
                ],
            ]),
        ]);

        $this->postSnsMessage($data)->assertOk();

        $subscriber = NewsletterSubscriber::query()->where('email', 'complainer@example.com')->firstOrFail();
        $this->assertSame('complained', $subscriber->status);
        $this->assertSame('complaint', $subscriber->ses_event_type);
    }

    public function test_a_repeated_bounce_event_is_idempotent(): void
    {
        NewsletterSubscriber::query()->create(['email' => 'repeat@example.com', 'status' => 'subscribed', 'consented_at' => now()]);

        $data = $this->signedSnsMessage([
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [['emailAddress' => 'repeat@example.com']],
                ],
            ]),
        ]);

        $this->postSnsMessage($data)->assertOk();
        $this->postSnsMessage($data)->assertOk();

        $this->assertSame(1, NewsletterSubscriber::query()->where('email', 'repeat@example.com')->count());
        $this->assertSame('bounced', NewsletterSubscriber::query()->where('email', 'repeat@example.com')->value('status'));
    }

    public function test_an_unknown_recipient_does_not_create_a_subscriber(): void
    {
        $data = $this->signedSnsMessage([
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [['emailAddress' => 'nobody-subscribed@example.com']],
                ],
            ]),
        ]);

        $response = $this->postSnsMessage($data);

        $response->assertOk();
        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_a_malformed_ses_event_is_handled_safely(): void
    {
        $data = $this->signedSnsMessage(['Message' => 'not valid json {{']);

        $response = $this->postSnsMessage($data);

        $response->assertOk();
        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }
}
