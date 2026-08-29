<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/development instructions for SEO.docx §4/§6: robots.txt must
 * reference the production sitemap and never be the mechanism protecting
 * private/admin content, and the sitemap must only ever include public,
 * published, indexable, canonical URLs.
 */
class RobotsAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_disallows_admin_and_references_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin');
        $response->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_sitemap_includes_home_and_published_pages(): void
    {
        $page = $this->page(['status' => PageStatus::Published->value]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(url('/'), false);
        $response->assertSee(route('pages.show', $page), false);
    }

    public function test_sitemap_excludes_draft_pages(): void
    {
        $draft = $this->page(['status' => PageStatus::Draft->value]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('pages.show', $draft), false);
    }

    public function test_sitemap_excludes_a_page_explicitly_marked_noindex(): void
    {
        $page = $this->page(['status' => PageStatus::Published->value]);
        $page->seo()->create(['robots' => 'noindex, nofollow']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('pages.show', $page), false);
    }

    /**
     * A page whose own SEO tab points its canonical URL somewhere else has
     * declared itself a non-canonical duplicate — the sitemap must never
     * list a "Non-canonical URL" under its own address (docs/development
     * instructions for SEO.docx §6).
     */
    public function test_sitemap_excludes_a_page_whose_canonical_url_points_elsewhere(): void
    {
        $page = $this->page(['status' => PageStatus::Published->value]);
        $page->seo()->create(['canonical_url' => 'https://example.com/somewhere-else']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('pages.show', $page), false);
    }

    public function test_sitemap_excludes_the_home_page_when_marked_noindex(): void
    {
        $home = $this->page(['slug' => 'home', 'status' => PageStatus::Published->value]);
        $home->seo()->create(['robots' => 'noindex, nofollow']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('<loc>'.url('/').'</loc>', false);
    }

    public function test_the_home_page_slug_itself_never_appears_in_the_sitemap(): void
    {
        $home = $this->page(['slug' => 'home', 'status' => PageStatus::Published->value]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('pages.show', $home), false);
    }

    /**
     * The "music" Page record exists purely as a content/Hero-banner source
     * for MusicController — its slug happens to collide with the Music
     * module's own dedicated /music route, which always wins (registered
     * ahead of the {page:slug} catch-all). Before this fix, both
     * Page::sitemapEntries() and MusicController::sitemapEntries() emitted
     * the identical "/music" <loc>, so a plain assertSee/assertDontSee can't
     * tell "present once" from "present twice" — this counts occurrences.
     */
    public function test_the_music_page_slug_produces_the_url_exactly_once_in_the_sitemap(): void
    {
        $this->page(['slug' => 'music', 'status' => PageStatus::Published->value]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<loc>'.route('music.index').'</loc>'));
    }

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'title' => 'Test Page',
            'slug' => 'test-page-'.uniqid(),
            'template' => PageTemplate::Standard->value,
            'status' => PageStatus::Draft->value,
        ], $overrides));
    }
}
