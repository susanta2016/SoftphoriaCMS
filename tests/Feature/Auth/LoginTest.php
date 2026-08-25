<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Public login (points 1/9 of the confirmed spec) — real Auth::attempt()
 * authentication, generic failure messaging, blocked-status rejection, and
 * the guest-cannot-reach-account / owner-can-reach-account boundary.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_is_accessible_to_guests_and_marked_noindex(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Log In');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_valid_credentials_authenticate_and_redirect_to_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_fail_with_a_generic_error_and_do_not_authenticate(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('These credentials do not match our records.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_an_unknown_email_fails_with_the_same_generic_error(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nobody@example.com',
            'password' => 'whatever123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('These credentials do not match our records.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_suspended_users_credentials_are_rejected_with_the_same_generic_message(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Suspended->value,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('These credentials do not match our records.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_banned_users_credentials_are_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Banned->value,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_admin_cannot_log_in_via_the_public_login_form(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'password' => Hash::make('password123')]);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $admin->roles()->attach($adminRole);

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('Admin accounts must sign in at /admin/login.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_guest_is_redirected_away_from_the_account_dashboard(): void
    {
        $response = $this->get(route('account.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_an_authenticated_user_can_reach_the_account_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_remember_me_sets_the_remember_cookie(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('account.dashboard'));
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_login_redirects_to_the_originally_intended_url_after_authenticating(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->get(route('account.profile.edit'));

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('account.profile.edit'));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), [
                'email' => "rate{$i}@example.com",
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => 'rate-blocked@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
