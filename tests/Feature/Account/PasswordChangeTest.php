<?php

namespace Tests\Feature\Account;

use App\Actions\Account\ChangeAccountPasswordAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AUTH-005 — self-service password change + "log out other sessions"
 * (ChangeAccountPasswordAction). Session-revocation assertions force
 * session.driver=database for the duration of the test (phpunit.xml's
 * default testing driver is 'array', which the action's database-only
 * branch intentionally no-ops against — see its own docblock).
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/account/password');

        $response->assertRedirect(route('login'));
    }

    public function test_the_page_is_noindexed(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/account/password');

        $response->assertSee('noindex', false);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('current-password')]);

        $response = $this->actingAs($user)->from('/account/password')->put('/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_a_correct_current_password_changes_it(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('current-password')]);

        $response = $this->actingAs($user)->put('/account/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('account.password.edit'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_a_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('current-password')]);

        $response = $this->actingAs($user)->from('/account/password')->put('/account/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_changing_password_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('current-password')]);

        $currentSessionId = 'current-session-id';
        $otherSessionId = 'other-session-id';

        foreach ([$currentSessionId, $otherSessionId] as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => base64_encode('test-payload'),
                'last_activity' => now()->timestamp,
            ]);
        }

        app(ChangeAccountPasswordAction::class)->handle(
            $user,
            'current-password',
            'new-password-123',
            $currentSessionId,
        );

        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_changing_password_does_not_revoke_sessions_on_a_non_database_driver(): void
    {
        config(['session.driver' => 'array']);

        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('current-password')]);

        // No 'sessions' table row is even expected here — this only proves
        // the action doesn't throw or misbehave when the driver isn't
        // 'database' (see the action's own docblock on this scope).
        app(ChangeAccountPasswordAction::class)->handle(
            $user,
            'current-password',
            'new-password-123',
            'irrelevant-session-id',
        );

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }
}
