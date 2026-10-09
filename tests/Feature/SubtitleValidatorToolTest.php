<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\SubtitleValidator;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Database\Seeders\SubtitleValidatorToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Subtitle Validator as a Softphoria Tool: registered functionality,
 * draft-only seeder that never overwrites, private preview, published page
 * SEO and heading outline, links that resolve, honest claims, and no
 * server-side upload path or subtitle storage. The validation engine is
 * covered by resources/js/tools/subtitle-validator/tests (node --test).
 */
class SubtitleValidatorToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/subtitle-validator';

    public function test_the_registry_discovers_the_validator_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('subtitle-validator');

        $this->assertInstanceOf(SubtitleValidator::class, $functionality);
        $this->assertSame('Subtitle Validator (v1.0.0)', app(ToolRegistry::class)->options()['subtitle-validator']);
        $this->assertSame('MultimediaApplication', $functionality->applicationCategory());
        $this->assertSame(['resources/js/tools/subtitle-validator.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame(12, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool), 'ready to publish from the admin');
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $tool->seo->update(['meta_title' => 'Admin title']);
        $this->seed(SubtitleValidatorToolSeeder::class);

        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame('Admin title', $tool->seo->refresh()->meta_title);
        $this->assertSame(12, $tool->faqs()->count());
        $this->assertSame(1, Tool::query()->where('slug', 'subtitle-validator')->count());
    }

    public function test_related_tools_are_only_tools_that_exist(): void
    {
        $this->assertSame(0, $this->seededTool()->relatedTools()->count(), 'no placeholder links');

        Tool::query()->where('slug', 'subtitle-validator')->delete();
        $this->seedRelatedTools();
        $tool = $this->seededTool();
        $this->assertSame(['social-video-safe-zone-checker', 'image-requirements-checker'], $tool->relatedTools()->pluck('slug')->all());
    }

    public function test_admins_preview_the_validator_noindexed(): void
    {
        $tool = $this->seededTool();

        $this->actingAs($this->admin())->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-tool-preview="true"', false)
            ->assertSee('data-sv-file', false);
    }

    public function test_the_published_page_has_the_validator_and_full_seo(): void
    {
        $html = $this->publishedHtml();

        $this->assertStringContainsString('<title>Free Subtitle Validator – Check SRT &amp; VTT Files Online</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Validate SRT and VTT subtitle files for timestamp errors, overlaps, reading speed and line length. Fix common problems and download corrected subtitles free.">', $html);
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Free Subtitle Validator: Check SRT &amp; VTT Files Online</h1>#', $html);
        $this->assertStringContainsString('"applicationCategory":"MultimediaApplication"', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertSame(12, substr_count($html, '"@type":"Question"'));
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringContainsString('subtitle-validator-', $html, 'the tool script is loaded');
        $this->assertStringNotContainsString('data-sv', $this->get('/')->getContent());

        preg_match_all('# id="([^"]+)"#', $html, $ids);
        $this->assertSame(array_unique($ids[1]), $ids[1], 'no duplicate ids');
    }

    public function test_the_heading_outline_and_internal_links(): void
    {
        $html = $this->publishedHtml();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        preg_match_all('#<(h[1-6])[^>]*>(.*?)</h[1-6]>#s', $main, $headings, PREG_SET_ORDER);
        $outline = array_map(fn (array $h): string => $h[1].' '.trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES)), $headings);
        $this->assertSame([
            'h1 Free Subtitle Validator: Check SRT & VTT Files Online',
            'h2 How it works',
            'h2 What Does the Subtitle Validator Check?',
            'h3 File structure', 'h3 Timestamps', 'h3 Timing and overlaps', 'h3 Reading speed', 'h3 Line length and lines per cue', 'h3 Encoding and hidden characters',
            'h2 Common Subtitle Errors and How to Fix Them',
            'h2 SRT vs VTT: Which Format Should You Use?',
            'h2 Common questions',
            'h2 Related tools',
            'h3 Social Video Safe Zone Checker',
            'h3 Image Requirements Checker',
            'h2 Need a Custom Web Tool or Business Application?',
        ], $outline, 'the tool interface adds no headings');

        preg_match_all('#href="(/[^"]*)"#', $main, $links);
        foreach (['/tools/social-video-safe-zone-checker', '/services/custom-software', '/contact'] as $expected) {
            $this->assertContains($expected, $links[1]);
        }
        foreach (array_unique($links[1]) as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_the_content_makes_no_claims_the_tool_cannot_back(): void
    {
        $tool = $this->seededTool();
        $copy = strip_tags(implode(' ', [$tool->introduction, $tool->how_it_works, $tool->additional_content, $tool->important_notes]))
            .' '.collect(SubtitleValidatorToolSeeder::faqs())->flatten()->implode(' ');

        foreach (['/Netflix/i', '/compliant/i', '/guarantee(?!s? that)(?! platform)/i', '/100% (?:private|accurate)/i', '/no network/i'] as $claim) {
            $this->assertDoesNotMatchRegularExpression($claim, $copy);
        }
        $this->assertStringContainsString('not the rules of any particular platform', $copy);
        $this->assertStringContainsString('checks SRT and WebVTT only', $copy);
        $this->assertStringContainsString('if you accept analytics cookies', $copy);
    }

    public function test_seeded_fields_fit_the_admin_limits(): void
    {
        $tool = $this->seededTool();

        $this->assertLessThanOrEqual(60, mb_strlen($tool->seo->meta_title));
        $this->assertLessThanOrEqual(160, mb_strlen($tool->seo->meta_description));
        $this->assertLessThanOrEqual(160, mb_strlen($tool->heading));
        $this->assertLessThanOrEqual(600, mb_strlen($tool->introduction));
        $this->assertLessThanOrEqual(300, mb_strlen($tool->short_description));
        $this->assertLessThanOrEqual(120, mb_strlen($tool->cta_heading));
        $this->assertLessThanOrEqual(300, mb_strlen($tool->cta_text));
        foreach (SubtitleValidatorToolSeeder::faqs() as [$question, $answer]) {
            $this->assertLessThanOrEqual(200, mb_strlen($question));
            $this->assertLessThanOrEqual(2000, mb_strlen($answer));
        }
    }

    public function test_there_is_no_server_side_upload_path_or_subtitle_storage(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            ->filter(fn (Route $route): bool => str_contains($route->uri(), 'tools') || str_contains($route->uri(), 'subtitle'))
            ->map(fn (Route $route): string => $route->uri())
            ->values()->all();

        $this->assertSame([], $writes);
        $this->assertFalse(collect(Schema::getTableListing())->contains(fn (string $table): bool => str_contains($table, 'subtitle')));

        $html = $this->publishedHtml();
        $tool = substr($html, strpos($html, 'data-sv '), 20000);
        $this->assertStringNotContainsString('<form method="post"', strtolower($tool));
        $this->assertStringNotContainsString('enctype="multipart/form-data"', $tool);
    }

    public function test_the_engine_never_uses_network_or_persistent_storage(): void
    {
        $sources = collect(glob(base_path('resources/js/tools/subtitle-validator/src/*.js')))
            ->push(base_path('resources/js/tools/subtitle-validator.js'))
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', 'localStorage', 'sessionStorage', 'indexedDB', 'innerHTML', 'insertAdjacentHTML', 'eval(', 'new Function'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sources, $forbidden);
        }
    }

    private function publishedHtml(): string
    {
        Service::query()->firstOrCreate(['slug' => 'custom-software'], ['title' => 'Custom Software', 'summary' => 'Software.', 'is_published' => true]);
        $this->admin();
        $this->seedRelatedTools();
        $tool = $this->seededTool();
        foreach (Tool::query()->get() as $each) {
            $this->assertSame([], app(ToolPublisher::class)->publish($each, null), $each->slug);
        }

        return $this->get(self::URL)->assertOk()->getContent();
    }

    private function seedRelatedTools(): void
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $this->seed(ImageRequirementsToolSeeder::class);
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SubtitleValidatorToolSeeder::class);

        return Tool::query()->where('slug', 'subtitle-validator')->sole();
    }
}
