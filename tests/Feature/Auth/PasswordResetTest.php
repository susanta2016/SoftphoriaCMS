<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * AUTH-004 — public password reset. Pure Laravel Password::broker() end to
 * end; the routes added here are what AppServiceProvider::
 * routeResetPasswordThroughEmailTemplates() has been waiting on.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_is_publicly_accessible(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
    }

    public function test_the_forgot_password_page_is_noindexed(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertSee('noindex', false);
    }

    public function test_requesting_a_reset_link_gives_the_same_message_for_an_existing_email(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status');
    }

    public function test_requesting_a_reset_link_gives_the_same_message_for_an_unknown_email(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'unknown@example.com']);

        $response->assertSessionHas('status');
    }

    public function test_the_reset_password_page_is_publicly_accessible(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Password::broker()->createToken($user);

        $response = $this->get("/reset-password/{$token}?email={$user->email}");

        $response->assertOk();
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        Event::fake();

        $user = User::factory()->create(['status' => 'active']);
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_resetting_does_not_log_the_user_in(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertGuest();
    }

    public function test_an_invalid_token_shows_a_generic_error(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->from('/reset-password/bad-token')->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_an_unconfirmed_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_forgot_password_rate_limit_is_enforced(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'someone@example.com']);
        }

        $response = $this->post('/forgot-password', ['email' => 'someone@example.com']);

        $response->assertStatus(429);
    }
}
