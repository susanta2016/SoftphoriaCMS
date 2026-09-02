<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public newsletter signup (footer form) — SubscribeToNewsletterAction's
 * own docblock covers the save/email behavior; this file covers the
 * controller's validation and spam protection. Spam protection: the same
 * honeypot pattern as ContactController::store() (`hp_website`), reused
 * per the project-wide rule that every public submission form gets one.
 */
class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_subscribe(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'jane@example.com',
        ]);

        $response->assertSessionHas('newsletter_status');

        $subscriber = NewsletterSubscriber::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('subscribed', $subscriber->status);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com'));
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
}
