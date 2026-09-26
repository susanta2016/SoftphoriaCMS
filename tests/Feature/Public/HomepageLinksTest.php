<?php

namespace Tests\Feature\Public;

use App\Models\Menu;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Features\Features;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Every homepage link lands on a real page: the /portfolio list, the
 * services pages, and a safe header call to action.
 */
class HomepageLinksTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_the_seeded_homepage_has_no_placeholder_section_links(): void
    {
        $this->seedSite();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="/services"', $html);
        $this->assertStringContainsString('href="/portfolio"', $html);
        $this->assertStringContainsString('href="/services#technologies"', $html);
        $this->assertStringNotContainsString('href="/#services"', $html);
        $this->assertStringNotContainsString('href="/#portfolio"', $html);

        // No section link in the page body is a "#" placeholder (the footer's
        // seeded social/legal placeholders are admin data, outside <main>).
        $this->assertStringContainsString('<main', $html);
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));
        $this->assertStringContainsString('Our Services', $main, 'sanity check: the sections are inside <main>');
        $this->assertStringNotContainsString('href="#"', $main);
    }

    public function test_every_internal_homepage_link_resolves(): void
    {
        $this->seedSite();
        $this->livePost(['slug' => 'a-post']);

        preg_match_all('/href="(\/[^"#?]*)/', $this->get('/')->getContent(), $matches);

        foreach (array_unique($matches[1]) as $path) {
            // CMS pages (/about, /privacy-policy…) are admin content not
            // seeded here; every module route must resolve.
            $route = app('router')->getRoutes()->match(Request::create($path));
            if (str_starts_with($path, '/build/') || $path === '/' || $route->getName() === 'pages.show') {
                continue;
            }

            $status = $this->get($path)->getStatusCode();
            $this->assertContains($status, [200, 302], "{$path} returned {$status}");
        }
    }

    public function test_the_portfolio_page_lists_published_projects_and_filters_by_category(): void
    {
        PortfolioItem::query()->create(['title' => 'Shop Build', 'category' => 'E-Commerce']);
        PortfolioItem::query()->create(['title' => 'Cloud Move', 'category' => 'Cloud']);
        PortfolioItem::query()->create(['title' => 'Secret Draft', 'category' => 'Cloud', 'is_published' => false]);

        $this->get('/portfolio')
            ->assertOk()
            ->assertSee('Shop Build')
            ->assertSee('Cloud Move')
            ->assertDontSee('Secret Draft')
            ->assertSee('"@type":"CollectionPage"', false);

        $this->get('/portfolio?category=Cloud')
            ->assertSee('Cloud Move')
            ->assertDontSee('Shop Build')
            ->assertSee('<link rel="canonical" href="'.url('/portfolio').'"', false);

        $this->get('/portfolio?category=Nope')->assertSee('Shop Build')->assertSee('Cloud Move');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/portfolio?category=Cloud')
            ->assertSee('data-async-region="portfolio"', false)
            ->assertDontSee('<html', false);
    }

    public function test_the_portfolio_page_follows_its_feature_switch_and_is_in_the_sitemap(): void
    {
        PortfolioItem::query()->create(['title' => 'Shop Build']);

        $this->get('/sitemap.xml')->assertSee(url('/portfolio'), false);

        app(Features::class)->set('portfolio', false);

        $this->get('/portfolio')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee(url('/portfolio'), false);
    }

    public function test_a_non_url_header_cta_falls_back_to_the_contact_page(): void
    {
        app(SettingsRepository::class)->set('general', 'header_cta_url', 'admin@softphoria.com');

        $this->get('/')->assertDontSee('href="admin@softphoria.com"', false)->assertSee('href="'.route('contact.index').'"', false);

        app(SettingsRepository::class)->set('general', 'header_cta_url', '/book');
        $this->get('/')->assertSee('href="/book"', false);
    }

    public function test_the_migration_rewrites_only_placeholder_links(): void
    {
        $this->seedSite();
        $sections = Page::query()->where('slug', 'home')->sole()->sections;

        // Pre-migration placeholders…
        $hero = $sections->firstWhere('section_type', 'hero');
        $hero->forceFill(['content_json' => [...$hero->content_json, 'secondary_cta_url' => '/#services']])->save();
        $portfolio = $sections->firstWhere('section_type', 'portfolio');
        $portfolio->forceFill(['content_json' => [...$portfolio->content_json, 'link_url' => '#']])->save();
        // …and one an admin customised, which must be kept.
        $cta = $sections->firstWhere('section_type', 'cta');
        $cta->forceFill(['content_json' => [...$cta->content_json, 'secondary_cta_url' => '/custom']])->save();
        Menu::query()->where('slug', 'primary-navigation')->sole()->items()->where('label', 'Portfolio')->update(['url' => '/#portfolio']);

        (require database_path('migrations/2026_09_26_160000_hook_homepage_links_to_real_pages.php'))->up();

        $this->assertSame('/services', $hero->fresh()->content_json['secondary_cta_url']);
        $this->assertSame('/portfolio', $portfolio->fresh()->content_json['link_url']);
        $this->assertSame('/custom', $cta->fresh()->content_json['secondary_cta_url']);
        $this->assertSame('/portfolio', Menu::query()->where('slug', 'primary-navigation')->sole()->items()->where('label', 'Portfolio')->value('url'));
    }

    private function seedSite(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);
    }
}
