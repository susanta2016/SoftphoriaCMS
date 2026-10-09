<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\ToolStatus;
use App\Models\Page;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\ImageRequirements;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\ImageSizeGuidesSeeder;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Image Requirements Checker as a Softphoria Tool: registered functionality,
 * draft-only seeder, private preview, published page/SEO, related tools that
 * exist, and no server-side upload or image storage. The checker's own logic
 * is covered by resources/js/tools/image-requirements/tests (node --test).
 */
class ImageRequirementsToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/image-requirements-checker';

    public function test_the_registry_discovers_the_checker_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('image-requirements');

        $this->assertInstanceOf(ImageRequirements::class, $functionality);
        $this->assertSame('Image Requirements Checker (v1.0.0)', app(ToolRegistry::class)->options()['image-requirements']);
        $this->assertSame('MultimediaApplication', $functionality->applicationCategory());
        $this->assertSame(['resources/js/tools/image-requirements.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame(11, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool), 'ready to publish from the admin');
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $this->seed(ImageRequirementsToolSeeder::class);
        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame(1, Tool::query()->where('slug', 'image-requirements-checker')->count());
    }

    public function test_related_tools_are_only_tools_that_exist(): void
    {
        $this->assertSame(0, $this->seededTool()->relatedTools()->count(), 'no placeholder links');

        Tool::query()->where('slug', 'image-requirements-checker')->delete();
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $this->seed(ImageRequirementsToolSeeder::class);
        $tool = Tool::query()->where('slug', 'image-requirements-checker')->sole();
        $this->assertSame(['social-video-safe-zone-checker'], $tool->relatedTools()->pluck('slug')->all());
    }

    public function test_admins_preview_the_checker_noindexed(): void
    {
        $tool = $this->seededTool();

        $this->actingAs($this->admin())->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-tool-preview="true"', false)
            ->assertSee('data-irc-root data-theme="light"', false);
    }

    public function test_the_published_page_has_the_checker_and_full_seo(): void
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('data-irc-root', $html);
        $this->assertStringContainsString('data-tool-functionality="image-requirements"', $html);
        $this->assertStringContainsString('<title>Image Requirements Checker: Size, Crop &amp; Format | Softphoria</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertStringContainsString('"applicationCategory":"MultimediaApplication"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringNotContainsString('multipart/form-data', $html);
        $this->assertStringNotContainsString('data-svsz-root', $html, 'the video checker is not loaded here');
        $this->assertStringNotContainsString('data-irc-root', $this->get('/')->getContent());
    }

    public function test_the_page_explains_what_it_checks_and_links_each_guide_once(): void
    {
        $tool = $this->seededTool();
        app(ToolPublisher::class)->publish($tool, null);
        $this->admin();
        $this->seed(ImageSizeGuidesSeeder::class);
        Page::query()->where('is_tool_guide', true)->update(['status' => PageStatus::Published->value, 'publish_at' => now()->subMinute()]);

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Image Requirements Checker: Size, Format &amp; Crop</h1>#', $html);
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));

        // Server-rendered content sections, in reading order, between the template's own headings
        preg_match_all('#<h2[^>]*>(.*?)</h2>#s', $html, $h2);
        $headings = array_values(array_intersect(array_map(fn (string $h): string => trim(html_entity_decode(strip_tags($h))), $h2[1]), [
            'How it works', 'Use cases', 'What this image checker checks', 'Supported image requirements',
            'Why check your image before uploading?', 'Where the requirements come from', 'Image size guides', 'Common questions',
        ]));
        $this->assertSame(['How it works', 'Use cases', 'What this image checker checks', 'Supported image requirements',
            'Why check your image before uploading?', 'Where the requirements come from', 'Image size guides', 'Common questions'], $headings);

        // Every guide is linked exactly once, and resolves
        foreach (['social-media-image-sizes', 'instagram-image-sizes', 'youtube-thumbnail-size', 'facebook-image-sizes', 'linkedin-image-sizes', 'x-twitter-image-sizes', 'pinterest-image-sizes', 'open-graph-image-size'] as $slug) {
            $this->assertSame(1, substr_count($html, 'href="/tools/'.$slug.'"'), $slug);
            $this->get('/tools/'.$slug)->assertOk();
        }

        // One structured-data block; the FAQ schema carries every visible question
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertSame(11, substr_count($html, '"@type":"Question"'));
    }

    public function test_there_is_no_server_side_upload_path_or_image_storage(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            // The SEO pre-launch checker's audit endpoint is that tool's, not an upload path.
            ->reject(fn (Route $route): bool => $route->getName() === 'tools.seo-checker.audit')
            ->filter(fn (Route $route): bool => str_contains($route->uri(), 'tools') || str_contains($route->uri(), 'image-requirements'))
            ->map(fn (Route $route): string => $route->uri())
            ->values()->all();

        $this->assertSame([], $writes);
        $this->assertFalse(collect(Schema::getTableListing())->contains(fn (string $table): bool => str_contains($table, 'image_requirement')));
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(ImageRequirementsToolSeeder::class);

        return Tool::query()->where('slug', 'image-requirements-checker')->sole();
    }
}
