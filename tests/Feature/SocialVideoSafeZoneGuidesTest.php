<?php

namespace Tests\Feature;

use App\Enums\BlogPostStatus;
use App\Enums\PageStatus;
use App\Models\BlogPost;
use App\Models\Page;
use Database\Seeders\SocialVideoSafeZoneGuidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The Safe Zone Checker's Phase 1 SEO cluster: three tool-guide CMS Pages
 * and one blog post, seeded as drafts. Checks the approved URLs, metadata,
 * evidence blocks, internal links, downloads and the content rules (no
 * "official" claims, no TikTok in the combined values, no ratings, no
 * person author, no BreadcrumbList yet — that is Phase 1B).
 */
class SocialVideoSafeZoneGuidesTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const GUIDES = [
        'instagram-reels-safe-zone' => [
            'h1' => 'Instagram Reels Safe Zone: Where the App Covers Your Video',
            'title' => 'Instagram Reels Safe Zone (1080×1920): Measured Margins | Softphoria',
            'values' => ['190 px', '160 px', '220 px', '750 px', 'y 1590 to y 1700'],
            'devices' => ['iPhone 14 Plus', 'Nokia 6.1 Plus'],
        ],
        'youtube-shorts-safe-zone' => [
            'h1' => 'YouTube Shorts Safe Zone: Where the App Covers Your Video',
            'title' => 'YouTube Shorts Safe Zone (1080×1920): Measured Margins | Softphoria',
            'values' => ['180 px', '170 px', '190 px', 'y 260 to y 380'],
            'devices' => ['iPhone 14 Plus', 'Samsung Galaxy A20s'],
        ],
        '1080x1920-safe-zone-guide' => [
            'h1' => '1080×1920 Safe Zone Guide for Reels &amp; Shorts',
            'title' => '1080×1920 Safe Zone Guide for Reels &amp; Shorts | Softphoria',
            'values' => ['190 px', '170 px', '220 px', 'Combined Reels + Shorts safe area'],
            'devices' => ['iPhone 14 Plus', 'Nokia 6.1 Plus', 'Samsung Galaxy A20s'],
        ],
    ];

    public function test_the_seeder_creates_drafts_only_and_never_overwrites(): void
    {
        $this->seedGuides();

        $this->assertSame(3, Page::query()->where('is_tool_guide', true)->where('status', PageStatus::Draft)->count());
        $this->assertSame(BlogPostStatus::Draft, BlogPost::query()->where('slug', SocialVideoSafeZoneGuidesSeeder::ARTICLE)->sole()->status);

        foreach (array_keys(self::GUIDES) as $slug) {
            $this->get("/tools/{$slug}")->assertNotFound();
        }
        $this->get('/blog/'.SocialVideoSafeZoneGuidesSeeder::ARTICLE)->assertNotFound();

        Page::query()->where('slug', 'instagram-reels-safe-zone')->sole()->update(['title' => 'Edited by an admin']);
        $this->seedGuides();
        $this->assertSame('Edited by an admin', Page::query()->where('slug', 'instagram-reels-safe-zone')->sole()->title);
        $this->assertSame(3, Page::query()->where('is_tool_guide', true)->count());
        $this->assertSame(1, BlogPost::query()->where('slug', SocialVideoSafeZoneGuidesSeeder::ARTICLE)->count());
    }

    public function test_published_guides_serve_the_approved_metadata_and_evidence(): void
    {
        $this->publishAll();

        foreach (self::GUIDES as $slug => $spec) {
            $html = $this->get("/tools/{$slug}")->assertOk()->getContent();

            $this->assertStringContainsString('<h1 class="mt-2 text-4xl font-bold tracking-tight text-brand-navy sm:text-5xl">'.$spec['h1'].'</h1>', $html, $slug);
            $this->assertStringContainsString('<title>'.$spec['title'].'</title>', $html, $slug);
            $this->assertStringContainsString('<link rel="canonical" href="'.url("/tools/{$slug}").'">', $html, $slug);
            $this->assertStringContainsString('name="description"', $html);

            foreach ([...$spec['values'], ...$spec['devices'], '1080 × 1920', 'Last verified:</strong> October 2026', 'not an official', 'account configuration and interface experiments'] as $needle) {
                $this->assertStringContainsString($needle, $html, "{$slug}: {$needle}");
            }

            $this->assertStringContainsString('href="'.SocialVideoSafeZoneGuidesSeeder::HUB.'"', $html, "{$slug} links to the checker");
            $this->assertStringNotContainsString('BreadcrumbList', $html, 'breadcrumbs are Phase 1B');
            $this->assertStringNotContainsString('aggregateRating', $html);
            $this->assertStringNotContainsString('"author"', $html, 'organisation authorship, no person');

            $this->get("/{$slug}")->assertNotFound();
        }
    }

    public function test_the_combined_guide_excludes_tiktok_and_never_calls_itself_universal(): void
    {
        $this->publishAll();

        $html = $this->get('/tools/1080x1920-safe-zone-guide')->assertOk()->getContent();

        $this->assertStringContainsString('TikTok is not included in these combined values, because TikTok remains provisional and unmeasured in version 1 of our data.', $html);
        $this->assertStringContainsString("derived from Softphoria's current measured Instagram Reels and YouTube Shorts interface zones", $html);
        $this->assertStringNotContainsStringIgnoringCase('universal', $html);
    }

    public function test_no_page_claims_its_values_are_official_or_calls_the_ad_figure_wrong(): void
    {
        $this->publishAll();

        foreach ([...array_map(fn ($slug) => "/tools/{$slug}", array_keys(self::GUIDES)), '/blog/'.SocialVideoSafeZoneGuidesSeeder::ARTICLE] as $url) {
            $text = strtolower(strip_tags($this->get($url)->assertOk()->getContent()));

            foreach (['is an official', 'are official instagram', 'are official youtube', 'official specification:', 'is wrong', 'are wrong', 'incorrect', 'guaranteed', '100%'] as $claim) {
                $this->assertStringNotContainsString($claim, $text, "{$url}: {$claim}");
            }
        }
    }

    public function test_every_internal_link_and_download_in_the_cluster_resolves(): void
    {
        $this->publishAll();

        $bodies = Page::query()->where('is_tool_guide', true)->with('sections')->get()
            ->flatMap(fn (Page $page) => $page->sections->map(fn ($s) => json_encode($s->content_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
            ->push(BlogPost::query()->where('slug', SocialVideoSafeZoneGuidesSeeder::ARTICLE)->value('body'))
            ->implode(' ');

        preg_match_all('#(?:href|src)=\\\\?"(/[^"\\\\]*)#', $bodies, $matches);
        $paths = array_unique($matches[1]);
        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            if (str_starts_with($path, '/downloads/')) {
                $this->assertFileExists(public_path(ltrim($path, '/')), $path);

                continue;
            }
            if (in_array($path, ['/about', SocialVideoSafeZoneGuidesSeeder::HUB], true)) {
                continue; // the About page and the checker are seeded separately
            }
            $this->get($path)->assertOk();
        }

        foreach (['instagram-reels', 'youtube-shorts', 'reels-shorts-combined'] as $name) {
            $this->assertStringContainsString("/downloads/social-video-safe-zone/softphoria-{$name}-safe-zone-1080x1920.png", $bodies);
        }
    }

    public function test_the_article_is_a_blog_post_with_three_labelled_source_types(): void
    {
        $this->publishAll();

        $html = $this->get('/blog/'.SocialVideoSafeZoneGuidesSeeder::ARTICLE)->assertOk()->getContent();

        $this->assertStringContainsString('<title>Why Social Video Safe-Zone Numbers Differ | Softphoria</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/blog/'.SocialVideoSafeZoneGuidesSeeder::ARTICLE).'">', $html);
        foreach (['Published platform guidance (ads)', 'Third-party figures', 'Softphoria observed', '288 px top, 672 px bottom, 48 px left, 192 px right', '35% of the height'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertNull(BlogPost::query()->where('slug', SocialVideoSafeZoneGuidesSeeder::ARTICLE)->value('author_id'));
    }

    private function seedGuides(): void
    {
        $this->admin();
        $this->seed(SocialVideoSafeZoneGuidesSeeder::class);
    }

    private function publishAll(): void
    {
        $this->seedGuides();
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);
        BlogPost::query()->where('slug', SocialVideoSafeZoneGuidesSeeder::ARTICLE)->update(['status' => BlogPostStatus::Published->value, 'published_at' => now()->subMinute()]);
    }
}
