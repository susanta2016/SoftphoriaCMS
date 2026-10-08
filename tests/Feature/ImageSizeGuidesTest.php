<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use Database\Seeders\ImageSizeGuidesSeeder;
use Database\Seeders\SocialVideoSafeZoneGuidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The Image Requirements Checker's SEO cluster: eight tool-guide CMS Pages
 * seeded as drafts. Checks URLs, unique titles/descriptions, H1s, indexing,
 * breadcrumbs and Article schema (no FAQPage, no ratings), internal links,
 * and that key values match the checker's requirements data.
 */
class ImageSizeGuidesTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const H1 = [
        'social-media-image-sizes' => 'Social Media Image Sizes &amp; Requirements for 2026',
        'instagram-image-sizes' => 'Instagram Image Sizes &amp; Requirements for 2026',
        'youtube-thumbnail-size' => 'YouTube Thumbnail Size &amp; Image Requirements for 2026',
        'facebook-image-sizes' => 'Facebook Image Sizes &amp; Requirements for 2026',
        'linkedin-image-sizes' => 'LinkedIn Image Sizes &amp; Requirements for 2026',
        'x-twitter-image-sizes' => 'X (Twitter) Image Sizes &amp; Requirements for 2026',
        'pinterest-image-sizes' => 'Pinterest Image Sizes &amp; Requirements for 2026',
        'open-graph-image-size' => 'Open Graph Image Size: 1200 × 630 Explained',
    ];

    public function test_the_seeder_creates_eight_drafts_and_never_overwrites(): void
    {
        $this->seedGuides();

        $guides = Page::query()->whereIn('slug', array_values(ImageSizeGuidesSeeder::GUIDES))->get();
        $this->assertCount(8, $guides);
        $this->assertTrue($guides->every(fn (Page $p): bool => $p->is_tool_guide && $p->status === PageStatus::Draft && $p->author_id === null));
        foreach (ImageSizeGuidesSeeder::GUIDES as $slug) {
            $this->get("/tools/{$slug}")->assertNotFound();
        }

        Page::query()->where('slug', 'instagram-image-sizes')->sole()->update(['title' => 'Edited by an admin']);
        $this->seedGuides();
        $this->assertSame('Edited by an admin', Page::query()->where('slug', 'instagram-image-sizes')->sole()->title);
        $this->assertSame(8, Page::query()->whereIn('slug', array_values(ImageSizeGuidesSeeder::GUIDES))->count());
    }

    public function test_published_guides_are_indexable_with_unique_seo_breadcrumbs_and_article_schema(): void
    {
        $this->publishAll();
        $titles = [];
        $descriptions = [];

        foreach (self::H1 as $slug => $h1) {
            $html = $this->get("/tools/{$slug}")->assertOk()->getContent();

            $this->assertStringContainsString(">{$h1}</h1>", $html, $slug);
            $this->assertSame(1, substr_count($html, '<h1'), "{$slug}: one H1");
            $this->assertStringContainsString('<link rel="canonical" href="'.url("/tools/{$slug}").'">', $html);
            $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
            $this->assertStringContainsString('<meta property="og:title"', $html);
            $this->assertStringContainsString('name="twitter:card"', $html);
            preg_match('#<title>(.*?)</title>#', $html, $t);
            preg_match('#<meta name="description" content="([^"]*)"#', $html, $d);
            $titles[] = $t[1];
            $descriptions[] = $d[1];

            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ld);
            $graph = json_decode($ld[1], true)['@graph'];
            $this->assertSame(['Article', 'BreadcrumbList'], array_column($graph, '@type'), $slug);
            $this->assertSame(['Home', 'Tools'], array_slice(array_column($graph[1]['itemListElement'], 'name'), 0, 2));
            $this->assertStringNotContainsString('FAQPage', $html, 'no FAQ markup on guides');
            $this->assertStringNotContainsString('aggregateRating', $html);

            // Answer first, then the checker; a visible FAQ and dated sources
            $this->assertStringContainsString('<strong>Quick answer:</strong>', $html);
            $this->assertStringContainsString('href="'.ImageSizeGuidesSeeder::CHECKER.'"', $html);
            $this->assertStringContainsString('Frequently asked questions', $html);
            $this->assertStringContainsString('Last reviewed:</strong> 8 October 2026', $html);
            $this->assertStringNotContainsString('data-irc-root', $html, 'guides do not mount the checker');
            $this->assertStringNotContainsString('resources/js/tools/image-requirements', $html, 'guides do not load the checker script');
            $this->assertDoesNotMatchRegularExpression('#/build/assets/image-requirements-[^"]+\.js#', $html);
        }

        $this->assertCount(8, array_unique($titles), 'unique titles');
        $this->assertCount(8, array_unique($descriptions), 'unique meta descriptions');
        $sitemap = $this->get('/sitemap.xml')->getContent();
        foreach (array_keys(self::H1) as $slug) {
            $this->assertStringContainsString(url("/tools/{$slug}"), $sitemap);
        }
    }

    public function test_every_internal_link_resolves_and_the_hub_links_every_platform_guide(): void
    {
        $this->publishAll();
        $this->seed(SocialVideoSafeZoneGuidesSeeder::class);
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);

        $bodies = Page::query()->whereIn('slug', array_values(ImageSizeGuidesSeeder::GUIDES))->with('sections')->get()
            ->flatMap(fn (Page $p) => $p->sections->map(fn ($s) => json_encode($s->content_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
            ->implode(' ');
        preg_match_all('#href=\\\\?"(/[^"\\\\]*)#', $bodies, $m);

        foreach (array_unique($m[1]) as $path) {
            if (in_array($path, [ImageSizeGuidesSeeder::CHECKER, '/tools/social-video-safe-zone-checker'], true)) {
                continue; // the tools themselves are seeded separately
            }
            $this->get($path)->assertOk();
        }

        $hub = Page::query()->where('slug', 'social-media-image-sizes')->sole()->sections->first()->content_json['body'];
        foreach (array_diff(ImageSizeGuidesSeeder::GUIDES, ['social-media-image-sizes']) as $slug) {
            $this->assertStringContainsString("href=\"/tools/{$slug}\"", $hub, $slug);
        }
    }

    public function test_guide_values_match_the_checkers_requirements_data(): void
    {
        $this->seedGuides();
        $profiles = file_get_contents(resource_path('js/tools/image-requirements/data/profiles.js'));
        $body = fn (string $slug): string => Page::query()->where('slug', $slug)->sole()->sections->first()->content_json['body'];

        // [guide, text in the guide, the same value in profiles.js]
        $pairs = [
            ['youtube-thumbnail-size', '640 px', "minWidth: { value: 640, level: 'hard' }"],
            ['youtube-thumbnail-size', '2048 × 1152', "minWidth: { value: 2048, level: 'hard' }"],
            ['youtube-thumbnail-size', '1235 × 338', '1235'],
            ['youtube-thumbnail-size', '6 MB', "6 * MB, level: 'hard'"],
            ['youtube-thumbnail-size', '1280 × 720', 'frame: { width: 1280, height: 720 }'],
            ['linkedin-image-sizes', '3 MB', "3 * MB, level: 'hard'"],
            ['linkedin-image-sizes', '1512 × 256', 'frame: { width: 1512, height: 256 }'],
            ['facebook-image-sizes', '400 × 150', "minHeight: { value: 150, level: 'hard' }"],
            ['facebook-image-sizes', '851 × 315', 'frame: { width: 851, height: 315 }'],
            ['instagram-image-sizes', '1080 × 1350', 'frame: { width: 1080, height: 1350 }'],
            ['x-twitter-image-sizes', '1500 × 500', 'frame: { width: 1500, height: 500 }'],
            ['pinterest-image-sizes', '1000 × 1500', 'frame: { width: 1000, height: 1500 }'],
            ['open-graph-image-size', '8 MB', "8 * MB, level: 'hard'"],
        ];
        foreach ($pairs as [$slug, $guideText, $profileText]) {
            $this->assertStringContainsString($guideText, $body($slug), "{$slug}: {$guideText}");
            $this->assertStringContainsString($profileText, $profiles, "profiles.js: {$profileText}");
        }

        // Uncertain values are never called requirements; OG size is never a protocol rule
        $og = $body('open-graph-image-size');
        $this->assertStringContainsString('isn\'t required by the Open Graph protocol', $og);
        $this->assertStringNotContainsString('required by the Open Graph protocol to', $og);
        $this->assertStringContainsString('Third-party recommendation', $body('pinterest-image-sizes'));
        $this->assertStringContainsString('not confirmed', $body('x-twitter-image-sizes'));
    }

    private function seedGuides(): void
    {
        $this->admin();
        $this->seed(ImageSizeGuidesSeeder::class);
    }

    private function publishAll(): void
    {
        $this->seedGuides();
        Page::query()->whereIn('slug', array_values(ImageSizeGuidesSeeder::GUIDES))->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);
    }
}
