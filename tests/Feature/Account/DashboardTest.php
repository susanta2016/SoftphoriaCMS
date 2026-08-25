<?php

namespace Tests\Feature\Account;

use App\Models\Role;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account dashboard (points 3/5/10 of the confirmed spec) — guest denied,
 * owner sees only their own real data, noindex, never in the sitemap.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_bare_account_redirects_a_signed_in_member_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/account');

        $response->assertRedirect(route('account.dashboard'));
    }

    public function test_bare_account_denies_a_guest(): void
    {
        $response = $this->get('/account');

        $response->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_their_own_name_email_and_membership_state(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('jane@example.com');
        $response->assertSee('Free Member');
    }

    public function test_a_pro_member_sees_their_active_membership_state(): void
    {
        $user = User::factory()->create();
        Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertSee('Pro Member');
    }

    public function test_the_dashboard_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_dashboard_is_never_present_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('account.dashboard'), false);
    }

    /**
     * An admin can only reach /account/* via their existing /admin session
     * (same `web` guard) — AuthenticateUserAction already refuses admin
     * credentials on the public login form itself. EnsureAccountIsUsable
     * redirects them to /admin instead of rendering the member account area.
     */
    public function test_an_admin_session_is_redirected_away_from_the_account_area(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->get(route('account.dashboard'));

        $response->assertRedirect('/admin');
    }
}
