<?php

namespace Tests\Feature\Registration;

use App\Actions\Registration\VerifyEmailAction;
use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Email verification (point 6 of the confirmed spec) — token-based,
 * single-use, 24h-expiring, hashed at rest, and fully independent of
 * Subscription state: a paid Pro user can have an active subscription
 * while still PendingVerification, and this file proves verifying never
 * touches the Subscription row at all.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_verifies_the_account_and_is_single_use(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PendingVerification->value, 'email_verified_at' => null]);
        $rawToken = 'a-valid-raw-token-1234567890';
        $this->createVerification($user, $rawToken);

        $response = $this->get(route('verification.verify', ['token' => $rawToken]));
        $response->assertOk();
        $response->assertSee('Email Verified');

        $user->refresh();
        $this->assertSame(UserStatus::Active->value, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(0, EmailVerification::query()->where('user_id', $user->id)->count());

        // Reusing the exact same link a second time must fail cleanly.
        $second = $this->get(route('verification.verify', ['token' => $rawToken]));
        $second->assertOk();
        $second->assertSee('Link Invalid or Expired');
    }

    /**
     * docs/development instructions for SEO.docx §5: a single-use
     * verification link is never content search should surface.
     */
    public function test_the_verification_page_is_marked_noindex(): void
    {
        $response = $this->get(route('verification.verify', ['token' => 'irrelevant-for-this-check']));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_an_unknown_token_fails_cleanly(): void
    {
        $response = $this->get(route('verification.verify', ['token' => 'totally-unknown-token']));

        $response->assertOk();
        $response->assertSee('Link Invalid or Expired');
    }

    public function test_an_expired_token_is_rejected_and_the_user_stays_pending(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
        $rawToken = 'an-expired-raw-token-1234567890';
        $this->createVerification($user, $rawToken, now()->subHour());

        $response = $this->get(route('verification.verify', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertSee('Link Invalid or Expired');

        $this->assertSame(UserStatus::PendingVerification->value, $user->refresh()->status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_the_token_is_hashed_at_rest_never_the_raw_value(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
        $rawToken = 'the-raw-secret-token-value-123456';
        $this->createVerification($user, $rawToken);

        $stored = EmailVerification::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotSame($rawToken, $stored->token);
        $this->assertSame(hash('sha256', $rawToken), $stored->token);
    }

    public function test_verifying_email_never_touches_subscription_state_either_direction(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $rawToken = 'independent-state-raw-token-123456';
        $this->createVerification($user, $rawToken);

        app(VerifyEmailAction::class)->handle($rawToken);

        // Verifying flips the user Active, but the Subscription row (and
        // therefore hasActiveMembership()) is completely untouched by it.
        $this->assertTrue($subscription->fresh()->isActive());
        $this->assertSame(UserStatus::Active->value, $user->fresh()->status);
    }

    public function test_a_pro_user_can_have_an_active_subscription_while_still_pending_verification(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
        Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $this->assertTrue($user->hasActiveMembership());
        $this->assertSame(UserStatus::PendingVerification->value, $user->status);
    }

    public function test_verification_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('verification.verify', ['token' => "unknown-{$i}"]));
        }

        $response = $this->get(route('verification.verify', ['token' => 'unknown-blocked']));

        $response->assertStatus(429);
    }

    private function createVerification(User $user, string $rawToken, ?Carbon $expiresAt = null): EmailVerification
    {
        $verification = new EmailVerification;
        $verification->user_id = $user->id;
        $verification->email = $user->email;
        $verification->token = hash('sha256', $rawToken);
        $verification->expires_at = $expiresAt ?? now()->addHours(24);
        $verification->save();

        return $verification;
    }
}
