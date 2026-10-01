<?php

namespace Tests\Feature\Site;

use App\Filament\Pages\AnalyticsTracking;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Analytics\AnalyticsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup → Analytics & Tracking (Google Analytics 4). The GA code
 * only ships inside a consent-gated <template>, never when the cookie
 * banner is off or for admins, and the banner lists the tool in use.
 */
class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    }

    public function test_non_admin_cannot_access_the_analytics_page(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/admin/website-setup/analytics')->assertForbidden();
    }

    public function test_admin_can_open_the_analytics_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/website-setup/analytics')
            ->assertOk()
            ->assertSee('GA4 Measurement ID');
    }

    public function test_admin_can_save_the_measurement_id(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AnalyticsTracking::class)
            ->fillForm(['ga4_id' => ' g-abc123xyz '])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('G-ABC123XYZ', app(SettingsRepository::class)->get('analytics', 'ga4_id'));
    }

    public function test_malformed_ids_are_rejected(): void
    {
        foreach (['UA-12345-1', 'G-<script>', 'GTM-ABC1234'] as $bad) {
            Livewire::actingAs($this->admin)
                ->test(AnalyticsTracking::class)
                ->fillForm(['ga4_id' => $bad])
                ->call('save')
                ->assertHasFormErrors(['ga4_id']);
        }

        $this->assertNull(app(SettingsRepository::class)->get('analytics', 'ga4_id'));
    }

    public function test_clearing_the_id_turns_google_analytics_off(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsTracking::class)
            ->fillForm(['ga4_id' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_no_tracking_markup_without_a_measurement_id(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-consent-scripts', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $html);
    }

    public function test_ga_code_is_shipped_only_inside_a_consent_gated_template(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<template data-consent-scripts="tracking" data-consent-tool="ga4">', $html);
        $this->assertStringContainsString('gtag/js?id=G-ABC123XYZ', $html);
        $this->assertStringContainsString("gtag('config','G-ABC123XYZ')", $html);
        $this->assertStringContainsString('data-consent-cookie-map', $html);
        $this->assertStringContainsString('"tracking":["_ga","_ga_*"]', $html);

        // The GA snippet never appears outside the inert template.
        $withoutTemplates = preg_replace('/<template data-consent-scripts.*?<\/template>/s', '', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $withoutTemplates);
    }

    public function test_every_public_layout_page_gets_the_tag(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        foreach (['/', '/register', '/music', '/podcast', '/inspirational-resources'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-consent-tool="ga4"', false);
        }
    }

    public function test_the_master_switch_turns_everything_off(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ', 'enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('data-consent-scripts', false);
    }

    public function test_nothing_loads_when_the_cookie_banner_is_disabled(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);
        app(SettingsRepository::class)->set('cookies', 'enabled', false, 'boolean');

        $this->get('/')->assertOk()->assertDontSee('data-consent-scripts', false);
    }

    public function test_admins_are_not_tracked_unless_the_option_is_off(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee('data-consent-scripts', false);

        $this->configure(['exclude_admins' => false]);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('data-consent-scripts', false);
    }

    public function test_signed_in_members_are_tracked_with_consent(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);
        $member = User::factory()->create(['status' => 'active']);

        $this->actingAs($member)->get('/')->assertOk()->assertSee('data-consent-tool="ga4"', false);
    }

    public function test_the_cookie_banner_lists_google_analytics_under_tracking_only_when_active(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Google Analytics 4');

        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        $this->get('/')->assertOk()
            ->assertSee('Tools in use')
            ->assertSee('Google Analytics 4')
            ->assertSee('Google LLC');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function configure(array $values): void
    {
        app(AnalyticsSettings::class)->save($values);
    }
}
