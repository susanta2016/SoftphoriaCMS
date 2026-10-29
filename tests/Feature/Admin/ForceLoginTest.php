<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin > Users "Force Login" (config('features.force_login_enabled')):
 * the flag, the server-side checks in ForceLoginController, the session
 * switch in ForceLoginAsUserAction, and its audit record.
 */
class ForceLoginTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['status' => UserStatus::Active->value, ...$attributes]);
    }

    private function enable(): void
    {
        config(['features.force_login_enabled' => true]);
    }

    public function test_the_feature_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('features.force_login_enabled'));
    }

    public function test_when_disabled_the_action_is_hidden(): void
    {
        $member = $this->member();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertTableActionHidden('forceLogin', $member);
    }

    public function test_when_disabled_the_endpoint_is_rejected_and_nothing_changes(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post(route('admin.users.force-login', $member))->assertNotFound();

        $this->assertTrue(Auth::user()->is($admin));
        $this->assertSame(0, AuditLog::query()->where('action', 'user.force_login')->count());
    }

    public function test_when_enabled_the_action_is_offered_only_for_eligible_members(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();
        $pending = $this->member(['status' => UserStatus::PendingVerification->value]);
        $otherAdmin = $this->admin();
        $suspended = $this->member(['status' => UserStatus::Suspended->value]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionVisible('forceLogin', $member)
            ->assertTableActionVisible('forceLogin', $pending)
            ->assertTableActionHidden('forceLogin', $otherAdmin)
            ->assertTableActionHidden('forceLogin', $admin)
            ->assertTableActionHidden('forceLogin', $suspended);
    }

    public function test_an_admin_switches_the_browser_to_the_member(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)
            ->post(route('admin.users.force-login', $member))
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHas('status');

        $this->assertTrue(Auth::guard('web')->user()->is($member));
        $this->assertFalse(Auth::guard('web')->user()->is($admin));
        $this->assertSame($member->getKey(), session()->get(Auth::guard('web')->getName()));

        $this->get(route('account.dashboard'))->assertOk();
    }

    public function test_after_the_switch_the_admin_panel_is_no_longer_accessible(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post(route('admin.users.force-login', $member));

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_the_old_admin_session_is_destroyed_and_the_session_id_and_csrf_token_regenerated(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();

        // A real, stored admin session, sent back with the request via its
        // session cookie — exactly what the admin's browser does.
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        session()->put(Auth::guard('web')->getName(), $admin->getKey());
        session()->save();
        $oldSessionId = session()->getId();
        $oldToken = session()->token();
        $this->assertStringContainsString((string) $admin->getKey(), (string) session()->getHandler()->read($oldSessionId));

        $this->withCookie(config('session.cookie'), $oldSessionId)
            ->post(route('admin.users.force-login', $member))
            ->assertRedirect(route('account.dashboard'));

        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertSame('', (string) session()->getHandler()->read($oldSessionId), 'The old admin session record must be destroyed.');

        // Replaying the old admin session cookie no longer authenticates
        // anyone: the admin panel sends it to the admin login page. (The
        // test harness reuses one in-memory session object between requests;
        // a real request starts empty, so clear it to simulate that.)
        session()->flush();
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $oldSessionId)
            ->get('/admin/users')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_switch_is_audited_with_the_real_admin_as_actor(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeader('User-Agent', 'AdminBrowser/1.0')
            ->post(route('admin.users.force-login', $member));

        $log = AuditLog::query()->where('action', 'user.force_login')->sole();
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame('User', $log->entity_type);
        $this->assertSame($member->getKey(), (int) $log->entity_id);
        $this->assertSame('203.0.113.50', $log->ip_address);
        $this->assertSame('AdminBrowser/1.0', $log->user_agent);
        $this->assertNotNull($log->created_at);
    }

    public function test_the_target_account_data_is_not_modified(): void
    {
        $this->enable();
        $admin = $this->admin();
        $member = $this->member();
        $before = $member->fresh()->only(['password', 'email', 'status', 'remember_token', 'last_login_at', 'updated_at']);
        $rolesBefore = $member->roles()->pluck('slug')->all();

        $this->actingAs($admin)->post(route('admin.users.force-login', $member));

        $after = $member->fresh();
        $this->assertSame($before['password'], $after->password);
        $this->assertSame($before['email'], $after->email);
        $this->assertSame($before['status'], $after->status);
        $this->assertSame($before['remember_token'], $after->remember_token);
        $this->assertEquals($before['last_login_at'], $after->last_login_at);
        $this->assertEquals($before['updated_at'], $after->updated_at);
        $this->assertSame($rolesBefore, $after->roles()->pluck('slug')->all());
    }

    public function test_a_member_cannot_invoke_the_endpoint(): void
    {
        $this->enable();
        $member = $this->member();
        $other = $this->member();

        $this->actingAs($member)->post(route('admin.users.force-login', $other))->assertForbidden();

        $this->assertTrue(Auth::user()->is($member));
        $this->assertSame(0, AuditLog::query()->where('action', 'user.force_login')->count());
    }

    public function test_a_guest_cannot_invoke_the_endpoint(): void
    {
        $this->enable();
        $member = $this->member();

        $this->post(route('admin.users.force-login', $member))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_inactive_admin_cannot_invoke_the_endpoint(): void
    {
        $this->enable();
        $admin = $this->admin();
        $admin->forceFill(['status' => UserStatus::Suspended->value])->save();
        $member = $this->member();

        $this->actingAs($admin)->post(route('admin.users.force-login', $member))->assertForbidden();
    }

    public function test_ineligible_targets_are_rejected_and_the_admin_stays_signed_in(): void
    {
        $this->enable();
        $admin = $this->admin();

        $targets = [
            'another admin' => $this->admin(),
            'self' => $admin,
            'suspended' => $this->member(['status' => UserStatus::Suspended->value]),
            'banned' => $this->member(['status' => UserStatus::Banned->value]),
            'deleted' => $this->member(['status' => UserStatus::Deleted->value]),
        ];

        foreach ($targets as $label => $target) {
            $this->actingAs($admin)
                ->post(route('admin.users.force-login', $target))
                ->assertRedirect(ListUsers::getUrl());

            $this->assertTrue(Auth::user()->is($admin), "Admin must stay signed in after the '{$label}' attempt.");
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'user.force_login')->count());
    }

    public function test_a_nonexistent_target_returns_not_found(): void
    {
        $this->enable();

        $this->actingAs($this->admin())->post('/admin/force-login/999999')->assertNotFound();
    }

    public function test_the_route_is_post_only_and_keeps_web_csrf_and_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.users.force-login');

        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);

        $this->enable();
        $member = $this->member();
        $this->actingAs($this->admin())->get('/admin/force-login/'.$member->getKey())->assertStatus(405);
    }

    /**
     * Laravel skips CSRF checks while running tests, so this swaps in the
     * real middleware with that bypass removed to prove a forged POST is
     * refused and a genuine one (matching session token) goes through.
     */
    public function test_a_post_without_a_valid_csrf_token_is_refused(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->enable();
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post(route('admin.users.force-login', $member))->assertStatus(419);
        $this->assertTrue(Auth::user()->is($admin));
        $this->assertSame(0, AuditLog::query()->where('action', 'user.force_login')->count());

        $this->actingAs($admin)
            ->withSession(['_token' => 'valid-test-token'])
            ->post(route('admin.users.force-login', $member), ['_token' => 'valid-test-token'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertTrue(Auth::user()->is($member));
    }
}
