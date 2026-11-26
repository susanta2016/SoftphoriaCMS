<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\FancyTextGenerator;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\FancyTextGeneratorToolSeeder;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Database\Seeders\UtmBuilderToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Fancy Text Generator as a Softphoria Tool: registered functionality,
 * draft-only seeder that never overwrites, private preview, published page
 * SEO and heading outline, symbol library, links that resolve, honest
 * claims, a style count that matches the engine, and no server-side path
 * for the visitor's text. The Unicode engine itself is covered by
 * resources/js/tools/fancy-text-generator/tests (node --test).
 */
class FancyTextGeneratorToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/fancy-text-generator';

    public function test_the_registry_discovers_the_generator_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('fancy-text-generator');

        $this->assertInstanceOf(FancyTextGenerator::class, $functionality);
        $this->assertSame('Fancy Text Generator (v1.0.0)', app(ToolRegistry::class)->options()['fancy-text-generator']);
        $this->assertSame('DesignApplication', $functionality->applicationCategory());
        $this->assertSame(['resources/js/tools/fancy-text-generator.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame(8, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool), 'ready to publish from the admin');
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $tool->seo->update(['meta_title' => 'Admin title']);
        $this->seed(FancyTextGeneratorToolSeeder::class);

        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame('Admin title', $tool->seo->refresh()->meta_title);
        $this->assertSame(8, $tool->faqs()->count());
        $this->assertSame(1, Tool::query()->where('slug', 'fancy-text-generator')->count());
    }

    public function test_related_tools_are_only_tools_that_exist(): void
    {
        $this->assertSame(0, $this->seededTool()->relatedTools()->count(), 'no placeholder links');

        Tool::query()->where('slug', 'fancy-text-generator')->delete();
        $this->seedRelatedTools();
        $tool = $this->seededTool();
        $this->assertSame(['image-requirements-checker', 'utm-builder', 'social-video-safe-zone-checker'], $tool->relatedTools()->pluck('slug')->all());
    }

    public function test_admins_preview_the_generator_noindexed(): void
    {
        $tool = $this->seededTool();

        $this->actingAs($this->admin())->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-tool-preview="true"', false)
            ->assertSee('data-ft-input', false);
    }

    public function test_the_published_page_has_the_generator_and_full_seo(): void
    {
        $html = $this->publishedHtml();

        $this->assertStringContainsString('<title>Fancy Text Generator – 100+ Stylish Fonts to Copy &amp; Paste</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Create 100+ fancy text styles: bold, cursive, gothic, bubble and aesthetic fonts. Copy and paste into Instagram, TikTok, WhatsApp and more. Free, no signup.">', $html);
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta name="twitter:card"', $html);
        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Fancy Text Generator – Copy and Paste Stylish Fonts</h1>#', $html);
        $this->assertStringContainsString('"applicationCategory":"DesignApplication"', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertSame(8, substr_count($html, '"@type":"Question"'));
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringContainsString('fancy-text-generator-', $html, 'the tool script is loaded');
        $this->assertStringContainsString('placeholder="Type or paste your text here..."', $html);
        $this->assertStringNotContainsString('data-ft', $this->get('/')->getContent());

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $json);
        $this->assertIsArray(json_decode($json[1], true, flags: JSON_THROW_ON_ERROR), 'structured data is valid JSON');

        preg_match_all('# id="([^"]+)"#', $html, $ids);
        $this->assertSame(array_unique($ids[1]), $ids[1], 'no duplicate ids');
    }

    public function test_the_symbol_library_is_rendered_without_javascript(): void
    {
        $html = $this->publishedHtml();

        foreach (FancyTextGenerator::symbols() as $groupId => [$label, $symbols]) {
            $this->assertStringContainsString('data-ft-symbol-group="'.$groupId.'"', $html);
            $this->assertGreaterThanOrEqual(15, count($symbols), $label);
            $this->assertSame(count($symbols), count(array_unique(array_column($symbols, 0))), "$label has no duplicates");
        }
        $this->assertCount(12, FancyTextGenerator::symbols());
        $this->assertStringContainsString('data-ft-symbol="♥"', $html);
        $this->assertStringContainsString('aria-label="Copy Shrug: ¯\_(ツ)_/¯"', $html);
    }

    public function test_the_heading_outline_and_internal_links(): void
    {
        $html = $this->publishedHtml();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        preg_match_all('#<(h[1-6])[^>]*>(.*?)</h[1-6]>#s', $main, $headings, PREG_SET_ORDER);
        $outline = array_map(fn (array $h): string => $h[1].' '.trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES)), $headings);
        $this->assertSame([
            'h1 Fancy Text Generator – Copy and Paste Stylish Fonts',
            'h2 Decorative Symbols to Copy and Paste',
            'h3 Hearts', 'h3 Stars', 'h3 Flowers', 'h3 Arrows', 'h3 Decorations', 'h3 Lines and dividers', 'h3 Music symbols',
            'h3 Gaming symbols', 'h3 Mathematical symbols', 'h3 Currency symbols', 'h3 Smileys', 'h3 Kaomoji',
            'h2 What Is a Fancy Text Generator?',
            'h2 How to Use the Fancy Text Generator',
            'h2 Where Can You Use Fancy Text?',
            'h2 Are Fancy Fonts Real Fonts?',
            'h2 Why Does Fancy Text Sometimes Display as Boxes?',
            'h2 Is Fancy Text Accessible?',
            'h2 How This Tool Handles Unicode',
            'h2 Common questions',
            'h2 Related tools',
            'h3 Image Requirements Checker',
            'h3 UTM Builder',
            'h3 Social Video Safe Zone Checker',
            'h2 Need a Custom Web Tool or Business Application?',
        ], $outline);

        preg_match_all('#href="(/[^"]*)"#', $main, $links);
        foreach (['/tools/image-requirements-checker', '/tools/utm-builder', '/contact'] as $expected) {
            $this->assertContains($expected, $links[1]);
        }
        foreach (array_unique($links[1]) as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_the_published_tool_is_listed_on_the_tools_hub(): void
    {
        $this->publishedHtml();

        $this->get('/tools')->assertOk()
            ->assertSee('href="'.url(self::URL).'"', false)
            ->assertSee('Fancy Text Generator');
    }

    public function test_the_content_makes_no_claims_the_tool_cannot_back(): void
    {
        $tool = $this->seededTool();
        $copy = strip_tags(implode(' ', [$tool->introduction, $tool->short_description, $tool->additional_content, $tool->important_notes]))
            .' '.collect(FancyTextGeneratorToolSeeder::faqs())->flatten()->implode(' ');

        foreach (['/guarantee(?!d to work everywhere)/i', '/100% (?:private|compatible|safe)/i', '/works everywhere/i', '/always (?:works|displays)/i', '/no network/i'] as $claim) {
            $this->assertDoesNotMatchRegularExpression($claim, $copy);
        }
        $this->assertStringContainsString('if you accept analytics cookies', $copy);
        $this->assertStringContainsString('Screen readers', $copy);

        // "100+" must stay true: count the styles in the engine.
        $engine = file_get_contents(base_path('resources/js/tools/fancy-text-generator/src/styles.js'));
        $this->assertGreaterThanOrEqual(100, preg_match_all("/^\s+S\('[a-z-]+', '/m", $engine));
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
        foreach (FancyTextGeneratorToolSeeder::faqs() as [$question, $answer]) {
            $this->assertLessThanOrEqual(200, mb_strlen($question));
            $this->assertLessThanOrEqual(2000, mb_strlen($answer));
        }
    }

    public function test_there_is_no_server_side_path_for_the_text(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            ->reject(fn (Route $route): bool => $route->getName() === 'tools.seo-checker.audit')
            ->filter(fn (Route $route): bool => str_contains($route->uri(), 'tools') || str_contains($route->uri(), 'fancy'))
            ->map(fn (Route $route): string => $route->uri())
            ->values()->all();

        $this->assertSame([], $writes);

        $html = $this->publishedHtml();
        $tool = substr($html, strpos($html, 'data-ft '), 60000);
        $this->assertStringNotContainsString('method="post"', strtolower($tool));
        $this->assertStringNotContainsString(' name="', $tool, 'no field would be submitted anywhere');
    }

    public function test_the_engine_never_uses_network_or_html_injection(): void
    {
        $sources = collect(glob(base_path('resources/js/tools/fancy-text-generator/src/*.js')))
            ->push(base_path('resources/js/tools/fancy-text-generator.js'))
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'sessionStorage', 'indexedDB', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sources, $forbidden);
        }
        // localStorage holds favourite style ids only, never the text.
        $this->assertSame(1, substr_count($sources, 'storage?.setItem(FAVOURITES_KEY'));
    }

    private function publishedHtml(): string
    {
        $this->admin();
        $this->seedRelatedTools();
        $this->seededTool();
        foreach (Tool::query()->get() as $each) {
            $this->assertSame([], app(ToolPublisher::class)->publish($each, null), $each->slug);
        }

        return $this->get(self::URL)->assertOk()->getContent();
    }

    private function seedRelatedTools(): void
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        ToolCategory::query()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing']);
        $this->seed(ImageRequirementsToolSeeder::class);
        $this->seed(UtmBuilderToolSeeder::class);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing']);
        $this->seed(FancyTextGeneratorToolSeeder::class);

        return Tool::query()->where('slug', 'fancy-text-generator')->sole();
    }
}
