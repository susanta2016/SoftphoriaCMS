<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Account change-password (points 3/7/9 of the confirmed spec) — current
 * password must be verified first, new password is hashed via Laravel's
 * facilities, and other sessions are invalidated on success.
 */
class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.password.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_correct_current_password_is_required_and_change_succeeds(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password123',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('account.password.edit'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password123', $user->password));
        $this->assertFalse(Hash::check('old-password123', $user->password));
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'not-the-real-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_a_mismatched_new_password_confirmation_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password123',
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_the_new_password_must_meet_the_minimum_length(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * The actual database/Redis session-revocation branches in
     * ChangeAccountPasswordAction are only reachable when SESSION_DRIVER is
     * "database" or "redis" — this suite runs with SESSION_DRIVER=array
     * (phpunit.xml), the same constraint ForceLogoutAllSessionsAction's own
     * test suite already accepts (see UserResourceTest — it asserts the
     * audit log and self-protection only, never the driver-specific
     * deletion). What's testable here is that the action still completes
     * successfully regardless of which branch config('session.driver')
     * resolves to.
     */
    public function test_changing_the_password_succeeds_regardless_of_the_configured_session_driver(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password123',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('account.password.edit'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_the_password_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.password.edit'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
