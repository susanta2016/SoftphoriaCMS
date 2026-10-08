<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\ImageKbOptimizer;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\ImageKbOptimizerToolSeeder;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\KbTargetGuidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Exact Image KB Optimizer as a Softphoria Tool: registered functionality,
 * draft-only seeder, preview, published page/SEO, related tools that exist,
 * and no server-side upload or image storage. The compression logic is
 * covered by resources/js/tools/image-kb-optimizer/tests (node --test).
 */
class ImageKbOptimizerToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/exact-image-kb-optimizer';

    public function test_the_registry_discovers_the_tool_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('image-kb-optimizer');

        $this->assertInstanceOf(ImageKbOptimizer::class, $functionality);
        $this->assertSame('Exact Image KB Optimizer (v1.0.0)', app(ToolRegistry::class)->options()['image-kb-optimizer']);
        $this->assertSame(['resources/js/tools/image-kb-optimizer.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_a_draft_ready_to_publish_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertSame(7, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool));
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $this->seed(ImageKbOptimizerToolSeeder::class);
        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame(1, Tool::query()->where('slug', 'exact-image-kb-optimizer')->count());
    }

    public function test_related_tools_are_only_tools_that_exist(): void
    {
        $this->assertSame(0, $this->seededTool()->relatedTools()->count());

        Tool::query()->where('slug', 'exact-image-kb-optimizer')->delete();
        $this->seed(ImageRequirementsToolSeeder::class);
        $this->seed(ImageKbOptimizerToolSeeder::class);
        $this->assertSame(['image-requirements-checker'], Tool::query()->where('slug', 'exact-image-kb-optimizer')->sole()->relatedTools()->pluck('slug')->all());
    }

    public function test_the_published_page_has_the_tool_and_full_seo_without_over_claiming(): void
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('data-iko-root', $html);
        $this->assertStringContainsString('<title>Compress Image to 50KB, 100KB or Any Size | Softphoria</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringNotContainsString('multipart/form-data', $html);
        $this->assertStringNotContainsString('data-irc-root', $html);

        // A target is a maximum, never promised as an exact byte count
        $text = strtolower(strip_tags($html));
        // (The FAQ may ask "Will the file be exactly 50 KB?" — its answer is what matters.)
        foreach (['compressed to exactly', 'is exactly 50 kb', 'exact byte', 'guaranteed', 'gdpr'] as $claim) {
            $this->assertStringNotContainsString($claim, $text, $claim);
        }
        $this->assertStringContainsString('at or below', $text);
    }

    public function test_popular_sizes_link_to_exactly_the_eight_target_guides(): void
    {
        $this->admin();
        $this->seed(ImageKbOptimizerToolSeeder::class);

        $content = Tool::query()->where('slug', 'exact-image-kb-optimizer')->sole()->additional_content;
        preg_match_all('#href="/tools/(compress-image-to-[^"]+)"#', $content, $m);

        $this->assertSame(array_keys(KbTargetGuidesSeeder::TARGETS), $m[1]);
    }

    public function test_there_is_no_server_side_upload_path_or_image_storage(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            ->filter(fn (Route $route): bool => str_contains($route->uri(), 'tools') || str_contains($route->uri(), 'compress') || str_contains($route->uri(), 'optimi'))
            ->map(fn (Route $route): string => $route->uri())->values()->all();

        $this->assertSame([], $writes);
        $this->assertFalse(collect(Schema::getTableListing())->contains(fn (string $t): bool => str_contains($t, 'compress') || str_contains($t, 'optimiz')));
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(ImageKbOptimizerToolSeeder::class);

        return Tool::query()->where('slug', 'exact-image-kb-optimizer')->sole();
    }
}
