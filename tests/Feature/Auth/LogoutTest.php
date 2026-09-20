<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUTH-002 — logout. POST-only, requires an authenticated session.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_a_guest_cannot_log_out(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
    }
}
