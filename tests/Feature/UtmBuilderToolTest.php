<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\ToolStatus;
use App\Models\Page;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\UtmBuilder;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\ImageSizeGuidesSeeder;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Database\Seeders\UtmBuilderToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * UTM Builder landing page: create-only draft seeder, published page SEO,
 * heading order, internal links that resolve, FAQ structured data, related
 * tools/service/CTA. The URL-tagging logic itself is covered by
 * resources/js/tools/utm-builder/tests (node --test).
 */
class UtmBuilderToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/utm-builder';

    public function test_the_registry_has_the_builder_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('utm-builder');

        $this->assertInstanceOf(UtmBuilder::class, $functionality);
        $this->assertSame('UTM Builder (v1.1.0)', app(ToolRegistry::class)->options()['utm-builder']);
        $this->assertSame(['resources/js/tools/utm-builder.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame(7, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool), 'ready to publish from the admin');
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $tool->seo->update(['meta_title' => 'Admin title']);
        $this->seed(UtmBuilderToolSeeder::class);

        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame('Admin title', $tool->seo->refresh()->meta_title);
        $this->assertSame(7, $tool->faqs()->count(), 'FAQs are not added twice');
        $this->assertSame(1, Tool::query()->where('slug', 'utm-builder')->count());
    }

    public function test_related_tools_and_service_are_only_ones_that_exist(): void
    {
        $tool = $this->seededTool();
        $this->assertSame(0, $tool->relatedTools()->count(), 'no placeholder links');
        $this->assertNull($tool->service_id);

        $tool->delete();
        $service = $this->digitalMarketing();
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $this->seed(ImageRequirementsToolSeeder::class);
        $tool = $this->seededTool();

        $this->assertSame(['image-requirements-checker', 'social-video-safe-zone-checker'], $tool->relatedTools()->pluck('slug')->all());
        $this->assertSame($service->id, $tool->service_id);
    }

    public function test_the_published_page_has_the_builder_and_full_seo(): void
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('data-utm-form', $html);
        $this->assertStringContainsString('<title>Free UTM Builder — Campaign URL Generator | Softphoria</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Build free UTM tracking URLs for Google Analytics. Add campaign source, medium, name and optional parameters to track email, social media and ad campaigns.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertStringContainsString('"softwareVersion":"1.1.0"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertSame(7, substr_count($html, '"@type":"Question"'));
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_the_content_follows_the_heading_plan_and_links_only_to_live_pages(): void
    {
        $this->digitalMarketing();
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $this->seed(ImageRequirementsToolSeeder::class);
        $tool = $this->seededTool();
        foreach (Tool::query()->get() as $each) {
            $this->assertSame([], app(ToolPublisher::class)->publish($each, null), $each->slug);
        }
        $this->admin();
        $this->seed(ImageSizeGuidesSeeder::class);
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);

        $html = $this->get(self::URL)->assertOk()->getContent();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Free UTM Builder: Create Google Analytics Campaign URLs</h1>#', $html);
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));

        // Headings in reading order: the template's own plus the content's
        preg_match_all('#<(h[23])[^>]*>(.*?)</h[23]>#s', $main, $headings, PREG_SET_ORDER);
        $outline = array_map(fn (array $h): string => $h[1].' '.trim(html_entity_decode(strip_tags($h[2]))), $headings);
        $this->assertSame([
            'h2 How it works',
            'h2 What are UTM parameters?',
            'h2 UTM parameters explained',
            'h2 UTM naming conventions and best practices',
            'h2 UTM examples for email, social media and paid ads',
            'h3 Email newsletter',
            'h3 Social media post',
            'h3 Paid search ad',
            'h2 Common questions',
            'h2 Related tools',
            'h3 Image Requirements Checker',
            'h3 Social Video Safe Zone Checker',
            'h2 Need help with digital marketing?',
        ], $outline);
        $this->assertSame(count($outline), count(array_unique($outline)), 'no duplicate headings');

        // The parameter table lists all six parameters
        $this->assertSame(1, substr_count($main, '<table>'));
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'] as $parameter) {
            $this->assertStringContainsString("<tr><td><code>{$parameter}</code></td>", $main);
        }

        // Every internal link in the content resolves
        preg_match_all('#href="(/[^"]*)"#', $main, $links);
        $this->assertContains('/tools/open-graph-image-size', $links[1]);
        $this->assertContains('/tools/social-media-image-sizes', $links[1]);
        $this->assertContains('/services/digital-marketing', $links[1]);
        foreach (array_unique($links[1]) as $path) {
            $this->get($path)->assertOk();
        }

        // Examples only use the reserved example domain
        preg_match_all('#<pre><code>(https?://[^/<]+)#', $main, $examples);
        $this->assertCount(4, $examples[1]);
        $this->assertSame(['https://www.example.com'], array_values(array_unique($examples[1])));

        // Related service card and the tool's own CTA
        $this->assertStringContainsString('Explore Digital Marketing', $main);
        $this->assertStringContainsString('Get help with campaign tracking, analytics setup and digital marketing strategy.', $main);
        $this->assertStringNotContainsString('Need help with your website?', $main);
    }

    public function test_seeded_seo_and_fields_fit_the_admin_limits(): void
    {
        $tool = $this->seededTool();

        $this->assertLessThanOrEqual(160, mb_strlen($tool->heading));
        $this->assertLessThanOrEqual(600, mb_strlen($tool->introduction));
        $this->assertLessThanOrEqual(120, mb_strlen($tool->cta_heading));
        $this->assertLessThanOrEqual(300, mb_strlen($tool->cta_text));
        $this->assertLessThanOrEqual(40, mb_strlen($tool->cta_label));
        foreach (UtmBuilderToolSeeder::faqs() as [$question, $answer]) {
            $this->assertLessThanOrEqual(200, mb_strlen($question));
            $this->assertLessThanOrEqual(2000, mb_strlen($answer));
        }
    }

    private function digitalMarketing(): Service
    {
        return Service::query()->create(['title' => 'Digital Marketing', 'slug' => 'digital-marketing', 'summary' => 'Marketing.', 'is_published' => true]);
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing']);
        $this->seed(UtmBuilderToolSeeder::class);

        return Tool::query()->where('slug', 'utm-builder')->sole();
    }
}
