<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CookiesPolicy;
use App\Models\Page as PageModel;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\PrivacyPolicyPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup's Cookies Policy manager (docs/Cookies Policy popup.docx) —
 * copy-driven admin page for the public site's consent banner and Cookies
 * Preferences Center, stored as `cookies` group rows via SettingsRepository,
 * same pattern as Settings' General/Footer/Email tabs.
 */
class CookiesPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_cookies_policy(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/website-setup/cookies-policy');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_cookies_policy_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CookiesPolicy::class)
            ->assertSuccessful();
    }

    public function test_default_copy_matches_the_approved_screenshots(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CookiesPolicy::class)
            ->assertSet('data.banner_title', 'We use cookies')
            ->assertSet('data.necessary_title', 'Strictly necessary cookies')
            ->assertSet('data.targeting_title', 'Targeting and advertising cookies');
    }

    public function test_admin_can_save_cookies_policy_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CookiesPolicy::class)
            ->fillForm([
                'enabled' => true,
                'banner_title' => 'Cookie notice',
                'banner_description' => 'Updated banner copy.',
                'privacy_title' => 'Your privacy is important to us',
                'privacy_description' => 'Updated privacy copy.',
                'necessary_title' => 'Strictly necessary cookies',
                'necessary_description' => 'Updated necessary copy.',
                'functionality_title' => 'Functionality cookies',
                'functionality_description' => 'Updated functionality copy.',
                'tracking_title' => 'Tracking cookies',
                'tracking_description' => 'Updated tracking copy.',
                'targeting_title' => 'Targeting and advertising cookies',
                'targeting_description' => 'Updated targeting copy.',
                'more_info_title' => 'More information',
                'more_info_description' => 'Updated more-info copy.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertSame('Cookie notice', $settings->get('cookies', 'banner_title'));
        $this->assertSame('Updated targeting copy.', $settings->get('cookies', 'targeting_description'));
    }

    public function test_disabling_the_banner_hides_it_from_the_public_site(): void
    {
        app(SettingsRepository::class)->set('cookies', 'enabled', false, 'boolean');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-cookie-banner', false);
    }

    public function test_public_home_page_shows_the_configured_banner_copy(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('cookies', 'enabled', true, 'boolean');
        $settings->set('cookies', 'banner_title', 'We value your privacy');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('We value your privacy');
    }

    public function test_seeder_creates_a_published_privacy_policy_page(): void
    {
        $this->admin();
        $this->seed(PrivacyPolicyPageSeeder::class);

        $page = PageModel::query()->where('slug', 'privacy-policy')->first();

        $this->assertNotNull($page);
        $this->assertSame('published', $page->status->value);

        $this->get('/privacy-policy')->assertOk();
    }

    public function test_more_information_tab_links_to_the_privacy_policy_page_once_seeded(): void
    {
        $this->admin();
        $this->seed(PrivacyPolicyPageSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.url('/privacy-policy').'"', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
