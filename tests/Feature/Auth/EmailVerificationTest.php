<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AUTH-003 — email verification. Uses the existing `email_verifications`
 * table (predates this ticket) via a SHA-256-hashed, single-use, 24h-
 * expiring token — never Laravel's native MustVerifyEmail/signed-URL
 * notification. See VerifyEmailAction's docblock for the GET-link
 * prefetch/scanner trade-off this accepts.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_verifies_the_account(): void
    {
        [$user, $rawToken] = $this->pendingUserWithToken();

        $response = $this->get("/verify-email/{$rawToken}");

        $response->assertOk();
        $response->assertSee('Email Verified');

        $user->refresh();
        $this->assertSame(UserStatus::Active->value, $user->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_token_is_single_use(): void
    {
        [, $rawToken] = $this->pendingUserWithToken();

        $this->get("/verify-email/{$rawToken}");
        $response = $this->get("/verify-email/{$rawToken}");

        $response->assertOk();
        $response->assertSee('Link Invalid or Expired');
    }

    public function test_an_unknown_token_fails(): void
    {
        $response = $this->get('/verify-email/not-a-real-token');

        $response->assertOk();
        $response->assertSee('Link Invalid or Expired');
    }

    public function test_an_expired_token_fails_and_is_removed(): void
    {
        [, $rawToken] = $this->pendingUserWithToken(now()->subDay());

        $response = $this->get("/verify-email/{$rawToken}");

        $response->assertOk();
        $response->assertSee('Link Invalid or Expired');
        $this->assertSame(0, EmailVerification::query()->count());
    }

    public function test_the_page_is_noindexed(): void
    {
        [, $rawToken] = $this->pendingUserWithToken();

        $response = $this->get("/verify-email/{$rawToken}");

        $response->assertSee('noindex', false);
    }

    public function test_resend_is_silent_for_an_unknown_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post('/verify-email/resend', ['email' => 'unknown@example.com']);

        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }

    public function test_resend_is_a_no_op_for_an_already_active_user(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->create(['status' => 'active', 'email' => 'active@example.com']);

        $this->post('/verify-email/resend', ['email' => $user->email]);

        Mail::assertNothingSent();
    }

    public function test_resend_issues_a_fresh_token_for_a_pending_user(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        [$user, $oldRawToken] = $this->pendingUserWithToken();

        $this->post('/verify-email/resend', ['email' => $user->email]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });

        $this->assertSame(1, EmailVerification::query()->where('user_id', $user->getKey())->count());
        $this->assertNull(EmailVerification::query()->where('token', hash('sha256', $oldRawToken))->first());
    }

    public function test_resend_more_than_the_rate_limit_is_throttled(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/verify-email/resend', ['email' => 'jane@example.com']);
        }

        $response = $this->post('/verify-email/resend', ['email' => 'jane@example.com']);

        $response->assertStatus(429);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function pendingUserWithToken(?\DateTimeInterface $expiresAt = null): array
    {
        $user = User::factory()->create(['status' => UserStatus::PendingVerification->value, 'email_verified_at' => null]);

        $rawToken = 'raw-test-token-'.$user->getKey();

        $verification = new EmailVerification;
        $verification->user_id = $user->getKey();
        $verification->email = $user->email;
        $verification->token = hash('sha256', $rawToken);
        $verification->expires_at = $expiresAt ?? now()->addHours(24);
        $verification->save();

        return [$user, $rawToken];
    }
}
