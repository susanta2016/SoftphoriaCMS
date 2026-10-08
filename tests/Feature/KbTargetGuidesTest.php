<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use Database\Seeders\ImageSizeGuidesSeeder;
use Database\Seeders\KbTargetGuidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The eight /tools/compress-image-to-… guides for the Exact Image KB
 * Optimizer: draft CMS guide pages (never Tools-module tools, never cards
 * on /tools), each linking to the one canonical tool with its target
 * preselected, with unique SEO and no FAQPage markup.
 */
class KbTargetGuidesTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_the_seeder_creates_eight_draft_guides_and_never_overwrites(): void
    {
        $this->seedGuides();

        $guides = Page::query()->whereIn('slug', array_keys(KbTargetGuidesSeeder::TARGETS))->get();
        $this->assertCount(8, $guides);
        $this->assertTrue($guides->every(fn (Page $p): bool => $p->is_tool_guide && $p->status === PageStatus::Draft && $p->author_id === null));
        foreach (array_keys(KbTargetGuidesSeeder::TARGETS) as $slug) {
            $this->get("/tools/{$slug}")->assertNotFound();
        }

        Page::query()->where('slug', 'compress-image-to-50kb')->sole()->update(['title' => 'Edited by an admin']);
        $this->seedGuides();
        $this->assertSame('Edited by an admin', Page::query()->where('slug', 'compress-image-to-50kb')->sole()->title);
        $this->assertSame(8, Page::query()->whereIn('slug', array_keys(KbTargetGuidesSeeder::TARGETS))->count());
    }

    public function test_published_guides_link_to_the_tool_with_their_target_and_have_unique_seo(): void
    {
        $this->publishAll();
        $titles = [];
        $descriptions = [];
        $bodies = [];

        foreach (KbTargetGuidesSeeder::TARGETS as $slug => [$label, $param]) {
            $html = $this->get("/tools/{$slug}")->assertOk()->getContent();

            $this->assertStringContainsString(">Compress an Image to {$label}</h1>", $html, $slug);
            $this->assertStringContainsString('<link rel="canonical" href="'.url("/tools/{$slug}").'">', $html);
            $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
            $this->assertStringContainsString('href="'.KbTargetGuidesSeeder::TOOL."?target={$param}\"", $html, "{$slug} preselects {$param}");
            $this->assertStringContainsString('<strong>Quick answer:</strong>', $html);
            $this->assertStringContainsString('Frequently asked questions', $html);
            $this->assertStringNotContainsString('FAQPage', $html);
            $this->assertStringNotContainsString('aggregateRating', $html);
            $this->assertStringNotContainsString('data-iko-root', $html, 'guides do not embed the tool');

            preg_match('#<title>(.*?)</title>#', $html, $t);
            preg_match('#<meta name="description" content="([^"]*)"#', $html, $d);
            $titles[] = $t[1];
            $descriptions[] = $d[1];
            $bodies[] = Page::query()->where('slug', $slug)->sole()->sections->first()->content_json['body'];
        }

        $this->assertCount(8, array_unique($titles));
        $this->assertCount(8, array_unique($descriptions));

        // Not near-duplicates: every page's FAQ questions are its own
        $faqSets = array_map(fn (string $b): string => implode('|', (preg_match_all('#<h3>(.*?)</h3>#', $b, $m) ? $m[1] : [])), $bodies);
        $this->assertCount(8, array_unique($faqSets));

        $sitemap = $this->get('/sitemap.xml')->getContent();
        foreach (array_keys(KbTargetGuidesSeeder::TARGETS) as $slug) {
            $this->assertStringContainsString(url("/tools/{$slug}"), $sitemap);
        }
    }

    public function test_guides_are_not_tools_and_do_not_appear_on_the_tools_hub(): void
    {
        $this->publishAll();

        $hub = $this->get('/tools')->assertOk()->getContent();
        foreach (array_keys(KbTargetGuidesSeeder::TARGETS) as $slug) {
            $this->assertStringNotContainsString("/tools/{$slug}", $hub, $slug);
            $this->assertDatabaseMissing('tools', ['slug' => $slug]);
        }
    }

    public function test_every_internal_link_resolves(): void
    {
        $this->publishAll();
        $this->seed(ImageSizeGuidesSeeder::class);
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);

        $links = Page::query()->whereIn('slug', array_keys(KbTargetGuidesSeeder::TARGETS))->with('sections')->get()
            ->flatMap(fn (Page $p) => $p->sections->map(fn ($s) => json_encode($s->content_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
            ->implode(' ');
        preg_match_all('#(?:href=\\\\?"|"cta_url":")(/[^"\\\\?]*)#', $links, $m);

        foreach (array_unique($m[1]) as $path) {
            if (in_array($path, [KbTargetGuidesSeeder::TOOL, '/tools/image-requirements-checker'], true)) {
                continue; // the tools themselves are seeded separately
            }
            $this->get($path)->assertOk();
        }
    }

    private function seedGuides(): void
    {
        $this->admin();
        $this->seed(KbTargetGuidesSeeder::class);
    }

    private function publishAll(): void
    {
        $this->seedGuides();
        Page::query()->whereIn('slug', array_keys(KbTargetGuidesSeeder::TARGETS))->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);
    }
}
