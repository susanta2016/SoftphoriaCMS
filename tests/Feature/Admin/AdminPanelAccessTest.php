<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login_page(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_the_admin_login_page_is_reachable(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }

    public function test_authenticated_user_without_admin_role_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_authenticated_admin_with_inactive_status_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $user->roles()->attach($adminRole);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_authenticated_active_user_with_admin_role_can_access_the_panel(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $user->roles()->attach($adminRole);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }
}
