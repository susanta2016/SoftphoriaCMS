<?php

namespace Tests\Feature\Registration;

use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * "light_post_submitted" (App\Actions\Registration\Concerns\
 * CreatesLightPostOnRegistration) — the registration-time "Share My Light"
 * acknowledgement. Registration itself is covered by FreeRegistrationTest/
 * ProRegistrationTest; this file covers only the new email.
 */
class LightPostAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharing_my_light_sends_a_light_post_acknowledgement_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.light@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => 'Grateful for this space.',
        ]);

        $user = User::query()->where('email', 'jane.light@example.com')->firstOrFail();

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email)
            && str_contains($mail->subjectLine, 'Light Post Has Been Shared'));
    }

    public function test_sharing_another_time_sends_no_light_post_acknowledgement(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.skip@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'skip',
        ]);

        Mail::assertNotSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => str_contains($mail->subjectLine, 'Light Post Has Been Shared'));
    }

    public function test_a_failed_light_post_acknowledgement_email_does_not_break_registration(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(TemplatedMailer::class, $mailer);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.resilient@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => 'Grateful even when email is down.',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.resilient@example.com')->firstOrFail();
        $this->assertSame(1, $user->lightPosts()->count());
    }
}
