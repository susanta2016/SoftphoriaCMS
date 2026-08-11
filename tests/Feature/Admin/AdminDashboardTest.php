<?php

namespace Tests\Feature\Admin;

use App\Models\ContactRequest;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_the_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_non_admin_user_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_dashboard_with_the_operational_widgets(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee('Total Users');
        $response->assertSee('Active User Accounts');
        $response->assertSee('Published Pages');
        $response->assertSee('New Contact Requests');
        $response->assertSee('Recent Administrative Activity');
        $response->assertSee('No administrative activity yet');
    }

    public function test_dashboard_renders_successfully_with_existing_core_data_present(): void
    {
        User::factory()->count(2)->create(['status' => 'suspended']);

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'default',
            'status' => 'published',
        ]);

        ContactRequest::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello there',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
    }

    public function test_up_endpoint_remains_available(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
