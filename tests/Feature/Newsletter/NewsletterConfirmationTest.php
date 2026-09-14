<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ConfirmNewsletterSubscriptionAction — the GET /newsletter/confirm/{token}
 * link a "newsletter_confirmation" email sends. Signup itself (issuing the
 * token) is covered in NewsletterSubscriptionTest.
 */
class NewsletterConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingSubscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create(array_merge([
            'email' => 'pending@example.com',
            'status' => 'pending',
            'confirmation_token_hash' => hash('sha256', 'raw-token-value'),
            'confirmation_expires_at' => now()->addHours(24),
        ], $attributes));
    }

    public function test_a_valid_token_confirms_the_subscription(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->pendingSubscriber();

        $response = $this->get(route('newsletter.confirm', ['token' => 'raw-token-value']));

        $response->assertOk();

        $subscriber = NewsletterSubscriber::query()->where('email', 'pending@example.com')->firstOrFail();
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->consented_at);
        $this->assertNull($subscriber->confirmation_token_hash);
        $this->assertNull($subscriber->confirmation_expires_at);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('pending@example.com'));
    }

    public function test_an_expired_token_does_not_subscribe(): void
    {
        $this->pendingSubscriber(['confirmation_expires_at' => now()->subHour()]);

        $response = $this->get(route('newsletter.confirm', ['token' => 'raw-token-value']));

        $response->assertOk();
        $this->assertSame('pending', NewsletterSubscriber::query()->where('email', 'pending@example.com')->value('status'));
    }

    public function test_an_invalid_token_does_not_subscribe(): void
    {
        $this->pendingSubscriber();

        $response = $this->get(route('newsletter.confirm', ['token' => 'not-the-right-token']));

        $response->assertOk();
        $this->assertSame('pending', NewsletterSubscriber::query()->where('email', 'pending@example.com')->value('status'));
    }

    public function test_reusing_a_consumed_token_does_not_create_another_subscription(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->pendingSubscriber();

        $this->get(route('newsletter.confirm', ['token' => 'raw-token-value']))->assertOk();

        $second = $this->get(route('newsletter.confirm', ['token' => 'raw-token-value']));

        $second->assertOk();
        $this->assertSame(1, NewsletterSubscriber::query()->where('email', 'pending@example.com')->count());
        Mail::assertSent(TemplatedNotificationMail::class, 1);
    }

    public function test_newsletter_confirm_is_rate_limited(): void
    {
        $this->pendingSubscriber();

        for ($i = 0; $i < 10; $i++) {
            $this->get(route('newsletter.confirm', ['token' => 'wrong-token']));
        }

        $response = $this->get(route('newsletter.confirm', ['token' => 'wrong-token']));

        $response->assertStatus(429);
    }
}
