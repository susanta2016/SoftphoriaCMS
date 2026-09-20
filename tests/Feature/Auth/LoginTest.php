<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUTH-002 — public login. AuthenticateUserAction rejects blocked
 * statuses and admin-role accounts from this form; the session is
 * regenerated on success (fixation prevention).
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_publicly_accessible_with_a_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_the_page_is_noindexed(): void
    {
        $response = $this->get('/login');

        $response->assertSee('noindex', false);
    }

    public function test_a_registered_user_can_log_in(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);

        $this->get('/login');
        $originalId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertNotSame($originalId, session()->getId());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_a_suspended_user_is_rejected(): void
    {
        $this->assertLoginRejectedForStatus('suspended');
    }

    public function test_a_locked_user_is_rejected(): void
    {
        $this->assertLoginRejectedForStatus('locked');
    }

    public function test_a_banned_user_is_rejected(): void
    {
        $this->assertLoginRejectedForStatus('banned');
    }

    public function test_a_deleted_status_user_is_rejected(): void
    {
        $this->assertLoginRejectedForStatus('deleted');
    }

    private function assertLoginRejectedForStatus(string $status): void
    {
        $user = User::factory()->create(['status' => $status, 'password' => bcrypt('password123')]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_an_admin_role_account_is_rejected_from_the_public_form(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_remember_me_sets_the_remember_cookie(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_submitting_more_than_the_rate_limit_is_throttled(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);
        $payload = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', $payload);
        }

        $response = $this->post('/login', $payload);

        $response->assertStatus(429);
    }

    public function test_a_logged_in_guest_route_redirects_an_authenticated_user_away(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect();
    }
}
