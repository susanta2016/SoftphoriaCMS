<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logout (points 8/9 of the confirmed spec) — POST-only, invalidates the
 * session, and immediately blocks re-entry to /account/*.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_log_out_via_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_the_account_area_is_inaccessible_immediately_after_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('logout'));

        $response = $this->get(route('account.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_logout_has_no_get_route_registered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertStatus(404);
    }
}
