<?php

namespace Tests\Feature\Public;

use App\Enums\PageTemplate;
use App\Http\Controllers\HomeController;
use App\Models\ContactRequest;
use App\Models\IpLocation;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Privacy Policy, Terms of Service and Cookie Policy: published content,
 * the legal page layout, footer links, the honest cookie banner, and the
 * security-data retention job the Privacy Policy promises.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $settings = app(SettingsRepository::class);
        $settings->set('general', 'site_name', 'Softphoria');
        $settings->set('contact', 'email', 'contact@softphoria.com');
        $settings->set('contact', 'address', "Monalisa Mansion, Nayabad Avenue\nKolkata, West Bengal, India");
    }

    public function test_the_seeder_publishes_all_three_policies_with_real_business_details(): void
    {
        $this->seed(LegalPagesSeeder::class);

        foreach (['privacy-policy', 'terms-of-service', 'cookie-policy'] as $slug) {
            $page = Page::query()->where('slug', $slug)->sole();
            $this->assertSame(PageTemplate::Legal, $page->template);

            $html = $this->get("/{$slug}")->assertOk()->getContent();
            $this->assertStringNotContainsString('{{', $html, "{$slug} has an unreplaced token");
            $this->assertStringContainsString('contact@softphoria.com', $html);
        }

        $this->get('/privacy-policy')
            ->assertSee('Digital Personal Data Protection Act, 2023')
            ->assertSee('a sole proprietorship')
            ->assertSee('Grievance Officer')
            ->assertSee('Data Protection Board of India')
            ->assertSee('General Data Protection Regulation')
            ->assertSee('Monalisa Mansion, Nayabad Avenue, Kolkata, West Bengal, India');

        $this->get('/terms-of-service')
            ->assertSee('Arbitration and Conciliation Act, 1996')
            ->assertSee('Kolkata, West Bengal, India')
            ->assertSee('New York Convention');

        $this->get('/cookie-policy')
            ->assertSee(config('session.cookie'))
            ->assertSee('XSRF-TOKEN')
            ->assertSee('cookie_consent');
    }

    public function test_legal_pages_use_the_legal_layout(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $this->get('/privacy-policy')
            ->assertSee('Last updated')
            ->assertSee('On this page')
            ->assertSee('href="#1-who-is-responsible-for-your-data"', false)
            ->assertSee('id="1-who-is-responsible-for-your-data"', false)
            ->assertSee('Related policies')
            ->assertSee('Terms of Service');
    }

    public function test_the_seeder_replaces_the_old_placeholder_but_never_an_edited_page(): void
    {
        // Saving a page recreates its sections, so always re-read through the page.
        $section = fn () => Page::query()->where('slug', 'privacy-policy')->sole()->sections()->first();
        $this->seed(LegalPagesSeeder::class);

        // Back to the old placeholder → replaced by a re-run.
        $section()->forceFill(['content_json' => ['body' => '<p><em>This is placeholder content — replace it.</em></p>']])->save();
        $this->seed(LegalPagesSeeder::class);
        $this->assertStringContainsString('Digital Personal Data Protection Act', $section()->content_json['body']);

        // Edited by an admin → left alone.
        $section()->forceFill(['content_json' => ['body' => '<p>Our lawyer-reviewed policy.</p>']])->save();
        $this->seed(LegalPagesSeeder::class);
        $this->assertSame('<p>Our lawyer-reviewed policy.</p>', $section()->content_json['body']);
    }

    public function test_the_footer_links_to_every_policy(): void
    {
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(LegalPagesSeeder::class);

        $this->get('/')
            ->assertSee('href="/privacy-policy"', false)
            ->assertSee('href="/terms-of-service"', false)
            ->assertSee('href="/cookie-policy"', false);
    }

    public function test_the_migration_fixes_the_terms_link_and_adds_the_cookie_policy_link_once(): void
    {
        $menu = Menu::query()->create(['slug' => 'footer-legal', 'name' => 'Footer Legal Links', 'is_active' => true]);
        foreach ([['Privacy Policy', '/privacy-policy'], ['Terms of Service', '#'], ['Sitemap', '/sitemap.xml']] as $i => [$label, $url]) {
            $menu->items()->create(['label' => $label, 'destination_type' => 'url', 'url' => $url, 'sort_order' => $i, 'is_enabled' => true]);
        }

        $migration = require database_path('migrations/2026_09_26_180000_publish_legal_pages_links_and_cookie_copy.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            ['Privacy Policy' => '/privacy-policy', 'Terms of Service' => '/terms-of-service', 'Cookie Policy' => '/cookie-policy', 'Sitemap' => '/sitemap.xml'],
            $menu->items()->orderBy('sort_order')->pluck('url', 'label')->all(),
        );
    }

    public function test_the_cookie_banner_no_longer_claims_tracking_or_targeted_ads(): void
    {
        $this->get('/')
            ->assertDontSee('targeted ads')
            ->assertSee('We do not use advertising or tracking cookies.');
    }

    public function test_the_migration_updates_only_unedited_saved_banner_copy(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('cookies', 'banner_description', 'We use cookies and other tracking technologies to improve your browsing experience on our website, to show you personalized content and targeted ads, to analyze our website traffic, and to understand where our visitors are coming from.');
        $settings->set('cookies', 'banner_title', 'Cookies at Softphoria');

        (require database_path('migrations/2026_09_26_180000_publish_legal_pages_links_and_cookie_copy.php'))->up();

        $this->assertSame(config('cookies_policy.banner_description'), $settings->get('cookies', 'banner_description'));
        $this->assertSame('Cookies at Softphoria', $settings->get('cookies', 'banner_title'));
    }

    public function test_the_retention_job_clears_old_security_data_and_keeps_recent(): void
    {
        $old = ContactRequest::query()->forceCreate(['name' => 'Old', 'email' => 'old@example.com', 'message' => 'Hi', 'ip_address' => '1.2.3.4', 'user_agent' => 'UA', 'created_at' => now()->subMonths(13)]);
        $recent = ContactRequest::query()->forceCreate(['name' => 'New', 'email' => 'new@example.com', 'message' => 'Hi', 'ip_address' => '5.6.7.8', 'user_agent' => 'UA']);
        IpLocation::query()->create(['ip' => '1.2.3.4', 'looked_up_at' => now()->subMonths(13)]);
        IpLocation::query()->create(['ip' => '5.6.7.8', 'looked_up_at' => now()]);

        $this->artisan('privacy:prune-security-data')->assertSuccessful();

        $this->assertNull($old->fresh()->ip_address);
        $this->assertNull($old->fresh()->user_agent);
        $this->assertSame('Hi', $old->fresh()->message, 'the enquiry itself is kept');
        $this->assertSame('5.6.7.8', $recent->fresh()->ip_address);
        $this->assertSame(['5.6.7.8'], DB::table('ip_locations')->pluck('ip')->all());
    }

    public function test_the_intro_video_uses_youtubes_privacy_enhanced_mode(): void
    {
        $method = new \ReflectionMethod(HomeController::class, 'resolveEmbedUrl');

        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/abc123', $method->invoke(null, 'https://www.youtube.com/watch?v=abc123'));
    }
}
