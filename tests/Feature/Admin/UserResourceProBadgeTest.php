<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Role;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin Users list — beside-the-name PRO badge. Renders purely off
 * User::hasActiveMembership() (itself Subscription::isActive()), so these
 * cases exist to lock in that the badge tracks that single existing rule
 * rather than a second, duplicated status calculation.
 */
class UserResourceProBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pro_user_shows_the_pro_badge_beside_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Active Pro']);
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);

        $html = UsersTable::renderNameWithProBadge($user->name, $user->fresh());

        $this->assertStringContainsString('Active Pro', $html);
        $this->assertStringContainsString('PRO', $html);
    }

    public function test_free_user_with_no_subscription_shows_no_pro_badge(): void
    {
        $user = User::factory()->create(['name' => 'Free User']);

        $html = UsersTable::renderNameWithProBadge($user->name, $user->fresh());

        $this->assertStringContainsString('Free User', $html);
        $this->assertStringNotContainsString('PRO', $html);
    }

    public function test_cancelled_at_period_end_but_still_within_the_paid_period_still_shows_the_pro_badge(): void
    {
        $user = User::factory()->create(['name' => 'Cancelling Pro']);
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
            'current_period_end' => now()->addDays(5),
        ]);

        $html = UsersTable::renderNameWithProBadge($user->name, $user->fresh());

        $this->assertStringContainsString('PRO', $html);
    }

    public function test_expired_subscription_shows_no_pro_badge(): void
    {
        $user = User::factory()->create(['name' => 'Expired Pro']);
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->subDay(),
        ]);

        $html = UsersTable::renderNameWithProBadge($user->name, $user->fresh());

        $this->assertStringNotContainsString('PRO', $html);
    }

    public function test_user_without_any_subscription_row_shows_no_pro_badge(): void
    {
        $user = User::factory()->create(['name' => 'No Subscription']);

        $this->assertNull($user->fresh()->subscription);

        $html = UsersTable::renderNameWithProBadge($user->name, $user->fresh());

        $this->assertStringNotContainsString('PRO', $html);
    }

    /**
     * Integration check that the badge is actually wired into the live
     * Users list table, not just reachable as a standalone helper.
     */
    public function test_the_users_list_renders_the_pro_badge_for_an_active_pro_member(): void
    {
        $admin = $this->admin();
        $proUser = User::factory()->create(['name' => 'Wired Pro Member']);
        Subscription::query()->create([
            'user_id' => $proUser->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);
        $freeUser = User::factory()->create(['name' => 'Wired Free Member']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSeeHtml('PRO')
            ->assertSee('Wired Pro Member')
            ->assertSee('Wired Free Member');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
