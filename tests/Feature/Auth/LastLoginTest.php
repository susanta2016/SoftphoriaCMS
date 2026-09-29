<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login as AdminLogin;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * users.last_login_at — recorded on every completed member or admin
 * sign-in, never shown in the admin panel or the member account area.
 */
class LastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_login_records_the_last_login_time_without_touching_updated_at(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => Hash::make('password123')]);
        $updatedAt = $user->updated_at;

        Carbon::setTestNow('2026-10-01 09:30:00');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('account.dashboard'));

        $user->refresh();
        $this->assertSame('2026-10-01 09:30:00', $user->last_login_at->toDateTimeString());
        $this->assertTrue($user->updated_at->equalTo($updatedAt));
    }

    public function test_a_failed_login_does_not_record_anything(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => Hash::make('password123')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_a_blocked_account_is_not_recorded_even_though_its_password_matched(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => Hash::make('password123')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123']);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_an_admin_turned_away_from_the_public_form_is_not_recorded(): void
    {
        $admin = $this->admin();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password123']);

        $this->assertNull($admin->fresh()->last_login_at);
    }

    public function test_an_admin_panel_login_records_the_last_login_time(): void
    {
        $admin = $this->admin();

        Livewire::test(AdminLogin::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_a_failed_admin_panel_login_is_not_recorded(): void
    {
        $admin = $this->admin();

        Livewire::test(AdminLogin::class)
            ->fillForm(['email' => $admin->email, 'password' => 'wrong-password'])
            ->call('authenticate');

        $this->assertNull($admin->fresh()->last_login_at);
    }

    public function test_the_last_login_time_is_not_exposed_in_the_admin_user_screen_or_the_account_area(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $member->forceFill(['last_login_at' => '2026-10-01 09:30:00'])->save();

        $this->assertArrayNotHasKey('last_login_at', $member->fresh()->toArray());

        $this->actingAs($member)->get('/account/dashboard')->assertOk()
            ->assertDontSee('2026-10-01')->assertDontSee('Last login', false);
        $this->actingAs($member)->get('/account/profile')->assertOk()
            ->assertDontSee('2026-10-01')->assertDontSee('Last login', false);

        $this->actingAs($this->admin());
        Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
            ->assertDontSee('2026-10-01')->assertDontSee('Last login');
        Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])
            ->assertDontSee('2026-10-01')->assertDontSee('Last login');
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active', 'password' => Hash::make('password123')]);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $admin;
    }
}
