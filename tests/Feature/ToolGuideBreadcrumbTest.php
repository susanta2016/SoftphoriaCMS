<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\ToolPublisher;
use Database\Seeders\SocialVideoSafeZoneGuidesSeeder;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Phase 1B: tool-guide CMS Pages output a BreadcrumbList (Home → Tools →
 * page) beside their Article, read here from the rendered JSON-LD. The
 * checker, ordinary CMS pages and blog posts keep their existing data.
 */
class ToolGuideBreadcrumbTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_each_safe_zone_guide_outputs_home_tools_page_breadcrumbs(): void
    {
        $this->admin();
        $this->seed(SocialVideoSafeZoneGuidesSeeder::class);
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);

        $guides = [
            'instagram-reels-safe-zone' => 'Instagram Reels Safe Zone: Where the App Covers Your Video',
            'youtube-shorts-safe-zone' => 'YouTube Shorts Safe Zone: Where the App Covers Your Video',
            '1080x1920-safe-zone-guide' => '1080×1920 Safe Zone Guide for Reels & Shorts',
        ];

        foreach ($guides as $slug => $title) {
            $nodes = $this->jsonLd($this->get("/tools/{$slug}")->assertOk()->getContent());

            $this->assertSame([
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tools', 'item' => url('/tools')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => url("/tools/{$slug}")],
            ], $this->only($nodes, 'BreadcrumbList')['itemListElement'], $slug);

            // The Article is unchanged: same headline (SEO title) and canonical.
            $article = $this->only($nodes, 'Article');
            $this->assertSame(Page::query()->where('slug', $slug)->sole()->seo->meta_title, $article['headline']);
            $this->assertSame(url("/tools/{$slug}"), $article['mainEntityOfPage']['@id']);
            $this->assertArrayNotHasKey('aggregateRating', $article);
        }
    }

    public function test_the_checker_page_keeps_its_own_breadcrumbs(): void
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $checker = Tool::query()->where('slug', 'social-video-safe-zone-checker')->sole();
        $this->assertSame([], app(ToolPublisher::class)->publish($checker, null));

        $nodes = $this->jsonLd($this->get('/tools/social-video-safe-zone-checker')->assertOk()->getContent());

        $this->assertSame(['WebApplication', 'FAQPage', 'BreadcrumbList'], array_column($nodes, '@type'));
        $this->assertSame(['Home', 'Tools', 'Social Video Safe Zone Checker'], array_column($this->only($nodes, 'BreadcrumbList')['itemListElement'], 'name'));
        $this->assertSame(url('/tools/social-video-safe-zone-checker'), $this->only($nodes, 'BreadcrumbList')['itemListElement'][2]['item']);
    }

    public function test_ordinary_cms_pages_keep_a_single_article_without_breadcrumbs(): void
    {
        Page::query()->create(['title' => 'About us', 'slug' => 'about-us', 'template' => 'standard', 'status' => PageStatus::Published]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringNotContainsString('BreadcrumbList', $html);
        $this->assertSame(['Article'], array_column($this->jsonLd($html), '@type'));
    }

    public function test_blog_posts_keep_their_existing_breadcrumbs(): void
    {
        $post = $this->livePost(['title' => 'Cloud costs explained', 'blog_category_id' => $this->category('Cloud')->id]);

        $nodes = $this->jsonLd($this->get($post->url())->assertOk()->getContent());

        $this->assertSame(['BlogPosting', 'BreadcrumbList'], array_column($nodes, '@type'));
        $this->assertSame(
            [['Home', url('/')], ['Blog', route('blog.index')], ['Cloud', $post->category->url()], ['Cloud costs explained', $post->url()]],
            array_map(fn (array $item): array => [$item['name'], $item['item']], $this->only($nodes, 'BreadcrumbList')['itemListElement']),
        );
    }

    /**
     * Every top-level JSON-LD node in the page (a @graph is flattened).
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'no JSON-LD on the page');

        $nodes = [];
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context']);
            array_push($nodes, ...($data['@graph'] ?? [$data]));
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function only(array $nodes, string $type): array
    {
        $matching = array_values(array_filter($nodes, fn (array $node): bool => ($node['@type'] ?? null) === $type));
        $this->assertCount(1, $matching, "exactly one {$type}");

        return $matching[0];
    }
}
