<?php

namespace Tests\Feature\Public;

use App\Filament\Pages\AnalyticsTracking;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Analytics\AnalyticsSettings;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup → Analytics & Tracking: GA4 / GTM / Clarity / Meta Pixel /
 * LinkedIn IDs, search-engine verification and custom code. Tracking code
 * only ships inside consent-gated <template>s, never when the cookie banner
 * is off or for admins, and the Cookie / Privacy Policy list what is in use.
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

    public function test_admin_can_save_tracking_ids(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AnalyticsTracking::class)
            ->fillForm([
                'ga4_id' => ' G-ABC123XYZ ',
                'clarity_id' => 'abcd1234ef',
                'google_site_verification' => 'verify-Token_1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertSame('G-ABC123XYZ', $settings->get('analytics', 'ga4_id'));
        $this->assertSame('abcd1234ef', $settings->get('analytics', 'clarity_id'));
        $this->assertSame('verify-Token_1', $settings->get('analytics', 'google_site_verification'));
    }

    public function test_malformed_ids_are_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AnalyticsTracking::class)
            ->fillForm([
                'ga4_id' => 'UA-12345-1',
                'gtm_id' => 'GTM<script>',
                'meta_pixel_id' => 'abc',
                'google_site_verification' => '"><script>',
            ])
            ->call('save')
            ->assertHasFormErrors(['ga4_id', 'gtm_id', 'meta_pixel_id', 'google_site_verification']);

        $this->assertNull(app(SettingsRepository::class)->get('analytics', 'ga4_id'));
    }

    public function test_no_tracking_markup_without_configured_tools(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-consent-scripts', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $html);
    }

    public function test_tracking_code_is_shipped_only_inside_consent_gated_templates(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ', 'meta_pixel_id' => '123456789012345']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<template data-consent-scripts="tracking" data-consent-tool="ga4">', $html);
        $this->assertStringContainsString('<template data-consent-scripts="targeting" data-consent-tool="meta_pixel">', $html);
        $this->assertStringContainsString('gtag/js?id=G-ABC123XYZ', $html);
        $this->assertStringContainsString('data-consent-cookie-map', $html);

        // Every tracking snippet sits inside a template, never as a live script.
        $withoutTemplates = preg_replace('/<template data-consent-scripts.*?<\/template>/s', '', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $withoutTemplates);
        $this->assertStringNotContainsString('fbevents.js', $withoutTemplates);
    }

    public function test_the_master_switch_turns_everything_off(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ', 'enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('data-consent-scripts', false);
    }

    public function test_nothing_loads_when_the_cookie_banner_is_disabled(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ', 'google_site_verification' => 'verify-token']);
        app(SettingsRepository::class)->set('cookies', 'enabled', false);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-consent-scripts', false)
            // Verification tags set no cookies, so they are always output.
            ->assertSee('<meta name="google-site-verification" content="verify-token">', false);
    }

    public function test_admins_are_not_tracked_unless_the_option_is_off(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee('data-consent-scripts', false);

        $this->configure(['exclude_admins' => false]);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('data-consent-scripts', false);
    }

    public function test_custom_code_is_gated_under_its_chosen_category(): void
    {
        $this->configure([
            'custom_head_code' => '<script>window.customHead = 1;</script>',
            'custom_body_code' => '<noscript>custom-body</noscript>',
            'custom_code_category' => 'targeting',
            'custom_code_description' => 'Our ad partner tag.',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<template data-consent-scripts="targeting" data-consent-tool="custom-head">', $html);
        $this->assertStringContainsString('<template data-consent-scripts="targeting" data-consent-tool="custom-body" data-consent-target="body">', $html);
        $this->assertStringContainsString('window.customHead = 1;', $html);
    }

    public function test_the_cookie_banner_names_the_tools_in_use(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(config('cookies_policy.banner_description'))
            ->assertDontSee('Google Analytics 4');
        $this->assertSame(3, substr_count($this->get('/')->getContent(), 'None — no cookies in this category'));

        $this->configure(['ga4_id' => 'G-ABC123XYZ']);

        $this->get('/')->assertOk()
            ->assertSee(config('cookies_policy.banner_description_with_analytics'))
            ->assertSee('Google Analytics 4');
        $this->assertSame(2, substr_count($this->get('/')->getContent(), 'None — no cookies in this category'));
    }

    public function test_the_policies_disclose_the_tools_in_use(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $this->get('/cookie-policy')->assertOk()
            ->assertSee('Analytics and marketing tools')
            ->assertSee('We do not currently use any analytics or marketing tools');

        $this->configure(['ga4_id' => 'G-ABC123XYZ', 'linkedin_partner_id' => '1234567']);

        foreach (['/cookie-policy', '/privacy-policy'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Google Analytics 4')
                ->assertSee('LinkedIn Insight Tag')
                ->assertSee('_ga (2 years)')
                ->assertDontSee('We do not currently use any analytics or marketing tools');
        }

        $this->get('/terms-of-service')->assertOk()->assertDontSee('Analytics and marketing tools');
    }

    public function test_migration_rewords_published_policies_and_keeps_edited_text(): void
    {
        $this->seed(LegalPagesSeeder::class);
        $wording = require database_path('seeders/data/legal-analytics-wording.php');

        // Put the pre-analytics sentences back, as on an already-published site.
        foreach ($wording as $slug => $pairs) {
            $this->rewriteBody($slug, fn (string $body): string => str_replace(
                array_column($pairs, 1), array_column($pairs, 0), $body,
            ));
        }
        $this->rewriteBody('cookie-policy', fn (string $body): string => str_replace(
            $wording['cookie-policy'][1][0], '<li>Edited by the owner.</li>', $body,
        ));

        $migration = require database_path('migrations/2026_09_26_210000_make_legal_texts_analytics_aware.php');
        $migration->up();
        $migration->up(); // idempotent

        $privacy = $this->body('privacy-policy');
        foreach ($wording['privacy-policy'] as [$old, $new]) {
            $this->assertStringContainsString($new, $privacy);
        }
        $this->assertSame(1, substr_count($privacy, 'Analytics and advertising providers'));

        $cookie = $this->body('cookie-policy');
        $this->assertStringContainsString($wording['cookie-policy'][0][1], $cookie);
        $this->assertStringContainsString('<li>Edited by the owner.</li>', $cookie);
        $this->assertStringNotContainsString($wording['cookie-policy'][1][1], $cookie);
    }

    private function configure(array $values): void
    {
        app(AnalyticsSettings::class)->save($values);
    }

    private function body(string $slug): string
    {
        return Page::query()->where('slug', $slug)->sole()->sections
            ->map(fn ($section) => $section->content_json['body'] ?? '')
            ->implode("\n");
    }

    private function rewriteBody(string $slug, callable $change): void
    {
        $pageId = Page::query()->where('slug', $slug)->value('id');

        foreach (DB::table('page_sections')->where('page_id', $pageId)->get() as $section) {
            $content = json_decode($section->content_json, true);

            if (is_string($content['body'] ?? null)) {
                $content['body'] = $change($content['body']);
                DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
            }
        }
    }
}
