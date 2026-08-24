<?php

namespace Tests\Feature\Registration;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Resend verification (Phase 1, confirmed) — never reveals whether an
 * email belongs to any account (same response for a pending account, an
 * already-verified one, and a completely unknown address), invalidates the
 * previous token, and is rate-limited.
 */
class ResendVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_issues_a_new_token_and_invalidates_the_old_one_for_a_pending_user(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
        $old = new EmailVerification;
        $old->user_id = $user->id;
        $old->email = $user->email;
        $old->token = hash('sha256', 'old-raw-token');
        $old->expires_at = now()->addHours(24);
        $old->save();

        $response = $this->post(route('verification.resend'), ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('resend_notice');

        $this->assertSame(1, EmailVerification::query()->where('user_id', $user->id)->count());
        $this->assertNotSame(
            hash('sha256', 'old-raw-token'),
            EmailVerification::query()->where('user_id', $user->id)->first()->token,
        );

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_resend_for_an_unknown_email_returns_the_same_generic_response_and_sends_nothing(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $knownResponse = $this->post(route('verification.resend'), ['email' => 'unknown@example.com']);

        $knownResponse->assertRedirect();
        $knownResponse->assertSessionHas('resend_notice');
        Mail::assertNothingSent();
        $this->assertSame(0, EmailVerification::query()->count());
    }

    public function test_resend_for_an_already_active_user_is_a_no_op_with_the_same_generic_response(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->create(['status' => UserStatus::Active->value]);

        $response = $this->post(route('verification.resend'), ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('resend_notice');
        Mail::assertNothingSent();
        $this->assertSame(0, EmailVerification::query()->where('user_id', $user->id)->count());
    }

    public function test_resend_requires_a_valid_email_format(): void
    {
        $response = $this->post(route('verification.resend'), ['email' => 'not-an-email']);

        $response->assertSessionHasErrors('email');
    }

    public function test_resend_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('verification.resend'), ['email' => "someone{$i}@example.com"]);
        }

        $response = $this->post(route('verification.resend'), ['email' => 'blocked@example.com']);

        $response->assertStatus(429);
    }
}
