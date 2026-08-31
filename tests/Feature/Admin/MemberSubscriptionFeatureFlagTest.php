<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\GlobalPricing;
use App\Models\Role;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Modules\Commerce\Models\Subscription;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * config('features.member_subscription_enabled') / MEMBER_SUBSCRIPTION_ENABLED
 * — proves the flag hides subscription/Pro-Membership UI across the admin
 * panel and the member account area (Phase 1: no paid membership) without
 * touching the underlying Stripe/subscription code, database tables, or
 * existing subscription records. The rest of the Subscription/Stripe/
 * webhook test suite runs with the flag forced true (phpunit.xml), proving
 * the underlying functionality survives being hidden.
 */
class MemberSubscriptionFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_subscriptions_resource_is_hidden_from_admin_navigation_when_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);

        $this->assertFalse(SubscriptionResource::shouldRegisterNavigation());
    }

    public function test_the_subscriptions_resource_is_visible_in_admin_navigation_when_enabled(): void
    {
        config(['features.member_subscription_enabled' => true, 'admin_ui.show_commerce_menu' => true]);

        $this->assertTrue(SubscriptionResource::shouldRegisterNavigation());
    }

    public function test_global_pricings_membership_section_is_hidden_when_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);

        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->assertDontSee('Pro Member')
            ->assertDontSee('Cancellation Information');
    }

    public function test_global_pricings_membership_section_is_shown_when_enabled(): void
    {
        config(['features.member_subscription_enabled' => true]);

        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->assertSee('Pro Member')
            ->assertSee('Cancellation Information');
    }

    public function test_saving_global_pricing_while_membership_is_hidden_does_not_error_and_keeps_the_stored_price(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('pricing', 'pro_member_monthly_price', '12.34');

        config(['features.member_subscription_enabled' => false]);

        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->fillForm(['music_per_song_price' => '1.29'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('1.29', $settings->get('pricing', 'music_per_song_price'));
        // The Membership section was never in the form, so save() must
        // never touch its settings keys at all — proven here by the
        // pre-existing price surviving untouched, not silently overwritten
        // with an empty value or erroring on a missing array key.
        $this->assertSame('12.34', $settings->get('pricing', 'pro_member_monthly_price'));
    }

    public function test_the_account_sidebar_hides_the_subscription_link_when_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('account.subscription'), false);
    }

    public function test_the_account_sidebar_shows_the_subscription_link_when_enabled(): void
    {
        config(['features.member_subscription_enabled' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertSee(route('account.subscription'), false);
    }

    public function test_the_dashboard_membership_card_is_hidden_when_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);
        $user = User::factory()->create();
        Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pro Member');
    }

    public function test_the_account_subscription_page_stays_directly_reachable_when_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);
        $user = User::factory()->create();
        Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)->get(route('account.subscription'));

        $response->assertOk();
        $response->assertSee('Pro Membership');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
