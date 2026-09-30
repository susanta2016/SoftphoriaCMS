<?php

namespace Tests\Feature\Public;

use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Models\Service;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO-001 — the data migration that fills in search titles/descriptions
 * (never overwriting existing copy), the Portfolio listing's settings-driven
 * metadata, and the Contact page's sitemap entry.
 */
class SoftphoriaSeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_30_120000_populate_softphoria_seo_metadata.php';

    public function test_the_migration_fills_empty_metadata_and_keeps_existing_copy(): void
    {
        $web = Service::create(['title' => 'Web Development', 'slug' => 'web-development', 'is_published' => true]);
        $cloud = Service::create(['title' => 'Cloud & DevOps', 'slug' => 'cloud-devops', 'is_published' => true]);
        $cloud->seo()->create(['meta_title' => 'Admin-written title']);
        $b2b = PortfolioItem::create(['title' => 'B2B E-Commerce Platform', 'slug' => 'b2b-e-commerce-platform', 'is_published' => true]);
        app(SettingsRepository::class)->set('blog', 'meta_title', 'Admin blog title');

        $this->runMigration();
        $this->runMigration();

        $this->assertSame('Web Development Services | Softphoria', $web->fresh()->seo->meta_title);
        $this->assertNotEmpty($web->fresh()->seo->meta_description);
        $this->assertSame('Admin-written title', $cloud->fresh()->seo->meta_title);
        $this->assertStringStartsWith('AWS cloud architecture', $cloud->fresh()->seo->meta_description);
        $this->assertSame('B2B E-Commerce Platform Project | Softphoria', $b2b->fresh()->seo->meta_title);
        $this->assertNull($b2b->fresh()->seo->og_title, 'OG/Twitter fields inherit from the meta fields.');

        $settings = app(SettingsRepository::class);
        $this->assertSame('Admin blog title', $settings->get('blog', 'meta_title'));
        $this->assertNotEmpty($settings->get('blog', 'meta_description'));
        $this->assertSame('Contact Softphoria | Discuss Your Project', $settings->get('contact', 'meta_title'));
    }

    public function test_the_migration_resets_only_self_referencing_page_canonicals(): void
    {
        $about = Page::create(['title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value, 'status' => 'published']);
        $about->seo()->create(['meta_title' => 'About', 'canonical_url' => 'http://localhost:8080/about']);
        $moved = Page::create(['title' => 'Moved', 'slug' => 'moved', 'template' => PageTemplate::Standard->value, 'status' => 'published']);
        $moved->seo()->create(['canonical_url' => 'https://partner.example.com/moved']);

        $this->runMigration();

        $this->assertNull($about->fresh()->seo->canonical_url);
        $this->assertSame('https://partner.example.com/moved', $moved->fresh()->seo->canonical_url);
        $this->get('/about')->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/about">', false);
    }

    public function test_the_portfolio_listing_uses_its_seo_settings_except_on_a_filtered_view(): void
    {
        PortfolioItem::create(['title' => 'Fintech App', 'slug' => 'fintech-app', 'category' => 'Fintech', 'is_published' => true]);
        $settings = app(SettingsRepository::class);
        $settings->set('portfolio', 'meta_title', 'Our Work | Softphoria');
        $settings->set('portfolio', 'meta_description', 'Projects we have delivered.');

        $this->get('/portfolio')
            ->assertSee('<title>Our Work | Softphoria</title>', false)
            ->assertSee('<meta name="description" content="Projects we have delivered.">', false);

        $this->get('/portfolio?category=Fintech')
            ->assertSee('<title>Fintech projects', false)
            ->assertSee('<link rel="canonical" href="'.route('portfolio.index').'">', false);
    }

    public function test_the_sitemap_lists_the_contact_page(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('contact.index').'</loc>', false);
    }

    private function runMigration(): void
    {
        (require database_path(self::MIGRATION))->up();
    }
}
