<?php

namespace Tests\Feature\Newsletter;

use App\Models\EmailTemplate;
use App\Models\NewsletterSubscriber;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public newsletter signup (footer form) — double opt-in.
 * SubscribeToNewsletterAction's own docblock covers the save/email
 * behavior; this file covers the controller's validation, messaging, spam
 * protection, and rate limiting. Confirming the emailed link is covered
 * separately in NewsletterConfirmationTest.
 */
class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_signup_creates_a_pending_subscriber_and_sends_a_confirmation_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'jane@example.com',
        ]);

        $response->assertSessionHas('newsletter_status');

        $subscriber = NewsletterSubscriber::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('pending', $subscriber->status);
        $this->assertNull($subscriber->consented_at);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->confirmation_token_hash);
        $this->assertNotNull($subscriber->confirmation_expires_at);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com'));
    }

    public function test_the_confirmation_email_contains_a_token_and_the_raw_token_is_never_stored(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        // The seeded default body is a generic placeholder (every Email
        // Template key gets one — see EmailTemplateSeeder's own docblock);
        // an admin adds the real {{confirmation_url}} link via Filament
        // before launch, exactly like email_verification's real link.
        // Simulate that here so the test can inspect the rendered link.
        EmailTemplate::query()
            ->where('notification_key', 'newsletter_confirmation')
            ->update(['html_body' => '<a href="{{confirmation_url}}">Confirm</a>']);

        $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'jane@example.com',
        ]);

        $subscriber = NewsletterSubscriber::query()->where('email', 'jane@example.com')->firstOrFail();

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($subscriber): bool {
            $rendered = $mail->render();
            preg_match('#/newsletter/confirm/([^"\s<]+)#', $rendered, $matches);
            $rawToken = $matches[1] ?? null;

            $this->assertNotNull($rawToken, 'Confirmation email did not contain a confirmation link.');
            $this->assertNotSame($rawToken, $subscriber->confirmation_token_hash);
            $this->assertSame($subscriber->confirmation_token_hash, hash('sha256', $rawToken));

            return true;
        });
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $response = $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        Mail::fake();

        $response = $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'bot@example.com',
            'hp_website' => 'https://spam.example.com',
        ]);

        // The bot gets the same success response a real visitor would —
        // no signal it was caught.
        $response->assertSessionHas('newsletter_status');
        $this->assertSame(0, NewsletterSubscriber::query()->count());
        Mail::assertNothingSent();
    }

    public function test_newsletter_subscribe_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('newsletter.subscribe'), ['email' => "rate{$i}@example.com"]);
        }

        $response = $this->post(route('newsletter.subscribe'), ['email' => 'rate-blocked@example.com']);

        $response->assertStatus(429);
    }

    public function test_an_already_subscribed_address_is_not_sent_another_confirmation_email(): void
    {
        Mail::fake();

        NewsletterSubscriber::query()->create([
            'email' => 'active@example.com',
            'status' => 'subscribed',
            'consented_at' => now(),
        ]);

        $this->from('/')->post(route('newsletter.subscribe'), ['email' => 'active@example.com']);

        Mail::assertNothingSent();
        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'active@example.com')->value('status'));
    }

    public function test_an_unsubscribed_address_signing_up_again_becomes_pending_and_requires_confirmation(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        NewsletterSubscriber::query()->create([
            'email' => 'came-back@example.com',
            'status' => 'unsubscribed',
            'consented_at' => now()->subMonth(),
            'unsubscribed_at' => now()->subDay(),
        ]);

        $this->from('/')->post(route('newsletter.subscribe'), ['email' => 'came-back@example.com']);

        $subscriber = NewsletterSubscriber::query()->where('email', 'came-back@example.com')->firstOrFail();
        $this->assertSame('pending', $subscriber->status);
        $this->assertNotNull($subscriber->confirmation_token_hash);
        Mail::assertSent(TemplatedNotificationMail::class);
    }

    public function test_a_bounced_subscriber_cannot_bypass_suppression_through_normal_signup(): void
    {
        Mail::fake();

        NewsletterSubscriber::query()->create([
            'email' => 'bounced@example.com',
            'status' => 'bounced',
            'ses_event_at' => now(),
            'ses_event_type' => 'bounce',
        ]);

        $response = $this->from('/')->post(route('newsletter.subscribe'), ['email' => 'bounced@example.com']);

        $response->assertSessionHas('newsletter_status');
        $this->assertSame('bounced', NewsletterSubscriber::query()->where('email', 'bounced@example.com')->value('status'));
        Mail::assertNothingSent();
    }

    public function test_a_complained_subscriber_cannot_bypass_suppression_through_normal_signup(): void
    {
        Mail::fake();

        NewsletterSubscriber::query()->create([
            'email' => 'complained@example.com',
            'status' => 'complained',
            'ses_event_at' => now(),
            'ses_event_type' => 'complaint',
        ]);

        $this->from('/')->post(route('newsletter.subscribe'), ['email' => 'complained@example.com']);

        $this->assertSame('complained', NewsletterSubscriber::query()->where('email', 'complained@example.com')->value('status'));
        Mail::assertNothingSent();
    }
}
