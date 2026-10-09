<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Service;
use App\Models\Tool;
use App\Tools\Functionalities\WebsiteSeoChecker;
use App\Tools\SeoChecker\HostResolver;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\ImageKbOptimizerToolSeeder;
use Database\Seeders\ImageRequirementsToolSeeder;
use Database\Seeders\UtmBuilderToolSeeder;
use Database\Seeders\WebsiteSeoCheckerToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The Website SEO/Metadata Pre-launch Checker as a Softphoria Tool:
 * registered functionality, draft-only seeder, private preview, published
 * page SEO/outline/links, honest claims, and the audit endpoint's gating,
 * validation, honeypot, rate limits and statelessness. The engine itself is
 * covered by WebsiteSeoCheckerAuditTest and tests/Unit/Tools/SeoChecker.
 */
class WebsiteSeoCheckerToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/website-seo-pre-launch-checker';

    private const AUDIT = '/tools/website-seo-checker/audit';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/' => Http::response('<!doctype html><html lang="en"><head><title>Example page title here</title></head><body><h1>Hi</h1></body></html>', 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('', 404),
        ]);
    }

    public function test_the_registry_discovers_the_checker_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('website-seo-checker');

        $this->assertInstanceOf(WebsiteSeoChecker::class, $functionality);
        $this->assertSame('Website SEO/Metadata Pre-launch Checker (v1.0.0)', app(ToolRegistry::class)->options()['website-seo-checker']);
        $this->assertSame('DeveloperApplication', $functionality->applicationCategory());
        $this->assertSame(['resources/js/tools/website-seo-checker.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
        $this->assertSame(route('tools.seo-checker.audit'), $functionality->viewData()['auditUrl']);
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertSame('seo', $tool->category->slug);
        $this->assertSame(10, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool));
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $this->seed(WebsiteSeoCheckerToolSeeder::class);

        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame(1, Tool::query()->where('slug', 'website-seo-pre-launch-checker')->count());
    }

    public function test_admins_preview_the_checker_noindexed_and_can_run_it_before_publishing(): void
    {
        $tool = $this->seededTool();
        $admin = $this->admin();

        $this->actingAs($admin)->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-seo-form', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="hp_website"', false);

        $this->actingAs($admin)->postJson(self::AUDIT, ['url' => 'https://example.com/'])->assertOk()->assertJsonPath('status', 'complete');
    }

    public function test_the_endpoint_only_works_while_the_tool_is_live(): void
    {
        $this->postJson(self::AUDIT, ['url' => 'https://example.com/'])->assertNotFound();

        $tool = $this->seededTool();
        $this->postJson(self::AUDIT, ['url' => 'https://example.com/'])->assertNotFound('a draft is not public');

        app(ToolPublisher::class)->publish($tool, null);
        $this->postJson(self::AUDIT, ['url' => 'https://example.com/'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'complete')
            ->assertJsonPath('final_url', 'https://example.com/');

        $this->getJson(self::AUDIT)->assertStatus(405);
    }

    public function test_invalid_and_blocked_addresses_get_safe_422_errors(): void
    {
        $this->publishedTool();

        foreach ([
            [[], 'invalid_url'],
            [['url' => ''], 'invalid_url'],
            [['url' => str_repeat('a', 2100)], 'invalid_url'],
            [['url' => 'ftp://example.com'], 'unsupported_scheme'],
            [['url' => 'http://localhost:8080/'], 'blocked_destination'],
            [['url' => 'https://user:pw@example.com/'], 'credentials_in_url'],
            [['url' => 'https://example.com/', 'intent' => 'production'], 'invalid_url'],
        ] as $i => [$payload, $code]) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])->postJson(self::AUDIT, $payload)->assertStatus(422)->assertJsonPath('error.code', $code);
            $this->assertStringNotContainsString('Exception', $response->getContent(), "case {$i}");
        }

        $blocked = $this->postJson(self::AUDIT, ['url' => 'http://10.0.0.1/'])->assertOk()->json();
        $this->assertSame('unavailable', $blocked['status']);
        $this->assertSame('blocked_destination', $blocked['error']['code']);
        $this->assertStringNotContainsString('10.0.0.1', $blocked['error']['message']);
    }

    public function test_the_honeypot_refuses_without_fetching(): void
    {
        $this->publishedTool();

        $this->postJson(self::AUDIT, ['url' => 'https://example.com/', 'hp_website' => 'spam'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_visitors_and_target_sites_are_rate_limited(): void
    {
        $this->publishedTool();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::AUDIT, ['url' => "https://example.com/?v={$i}"])->assertOk();
        }
        $this->postJson(self::AUDIT, ['url' => 'https://example.com/'])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');

        // Same target site from different visitors: capped per site per minute.
        $statuses = [];
        for ($i = 0; $i < 3; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson(self::AUDIT, ['url' => 'https://example.com/'])->status();
        }
        $this->assertSame([200, 429, 429], $statuses, '5 + 1 = the 6 audits per minute allowed for one site');
    }

    public function test_nothing_is_stored(): void
    {
        $this->publishedTool();
        $tables = Schema::getTableListing();

        $this->postJson(self::AUDIT, ['url' => 'https://example.com/'])->assertOk();

        $this->assertSame($tables, Schema::getTableListing());
        $this->assertFalse(collect($tables)->contains(fn (string $table): bool => str_contains($table, 'audit_report') || str_contains($table, 'seo_check')));
    }

    public function test_the_published_page_has_the_checker_and_full_seo(): void
    {
        $html = $this->publishedHtml();

        $this->assertStringContainsString('<title>Website SEO Checker &amp; Pre-Launch Audit | Softphoria</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Website SEO/Metadata Pre-launch Checker</h1>#', $html);
        $this->assertStringContainsString('"applicationCategory":"DeveloperApplication"', $html);
        $this->assertSame(10, substr_count($html, '"@type":"Question"'));
        $this->assertStringContainsString('website-seo-checker-', $html, 'the tool script is loaded');
        $this->assertStringContainsString('action="'.route('tools.seo-checker.audit').'"', $html);
        $this->assertStringNotContainsString('data-seo-form', $this->get('/')->getContent());

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
            'h1 Website SEO/Metadata Pre-launch Checker',
            'h2 How it works',
            'h2 Why Run an SEO Check Before Launch?',
            'h2 What the Checker Analyses',
            'h3 HTTP and URL', 'h3 Metadata', 'h3 Indexability and crawling', 'h3 Social sharing', 'h3 On-page structure', 'h3 Server response',
            'h2 How to Read the Report',
            'h2 Common Pre-launch SEO Mistakes',
            'h2 Website Launch Checklist',
            'h2 When to Ask for Professional Help',
            'h2 Common questions',
            'h2 Related tools',
            'h3 Image Requirements Checker',
            'h3 Exact Image KB Optimizer',
            'h3 UTM Builder',
            'h2 Launching or Redesigning a Website?',
        ], $outline, 'the tool interface adds no headings');

        preg_match_all('#href="(/[^"]*)"#', $main, $links);
        foreach (['/tools/image-requirements-checker', '/tools/exact-image-kb-optimizer', '/tools/utm-builder', '/services/web-development', '/services/cms-content-platforms', '/services/digital-marketing', '/contact'] as $expected) {
            $this->assertContains($expected, $links[1]);
        }
        foreach (array_unique($links[1]) as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_the_content_makes_no_claims_the_tool_cannot_back(): void
    {
        $tool = $this->seededTool();
        $copy = strip_tags(implode(' ', [$tool->introduction, $tool->how_it_works, $tool->additional_content, $tool->important_notes, $tool->short_description]))
            .' '.collect(WebsiteSeoCheckerToolSeeder::faqs())->flatten()->implode(' ');

        foreach (['/we guarantee|guaranteed (?:rankings|results|indexing|rich)/i', '/rich results? (?:will|are guaranteed)/i', '/measures? Core Web Vitals/i', '/crawls? (?:your|the) (?:entire|whole) (?:site|website)/i', '/100%/'] as $claim) {
            $this->assertDoesNotMatchRegularExpression($claim, $copy);
        }
        $this->assertStringContainsString('not whether Google has indexed it', $copy);
        $this->assertStringContainsString('doesn\'t run JavaScript', $copy);
        $this->assertStringContainsString('not a Google score', $copy);
        $this->assertStringContainsString('are not stored', $copy);
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
        foreach (WebsiteSeoCheckerToolSeeder::faqs() as [$question, $answer]) {
            $this->assertLessThanOrEqual(200, mb_strlen($question));
            $this->assertLessThanOrEqual(2000, mb_strlen($answer));
        }
    }

    public function test_the_browser_script_renders_remote_text_safely(): void
    {
        $sources = collect(glob(base_path('resources/js/tools/website-seo-checker/src/*.js')))
            ->push(base_path('resources/js/tools/website-seo-checker.js'))
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function', 'localStorage', 'sessionStorage', 'indexedDB', 'sendBeacon'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sources, $forbidden);
        }
        $this->assertSame(1, substr_count($sources, 'fetch('), 'one request: the audit endpoint');
        preg_match_all('/trackToolEvent(?:Once)?\((.*?)\);/s', $sources, $events);
        foreach ($events[1] as $event) {
            foreach (['url', 'final_url', 'submitted_url', 'evidence', 'title', 'value'] as $field) {
                $this->assertDoesNotMatchRegularExpression('/\b'.$field.'\b/', $event, "analytics never carry the address or findings: {$event}");
            }
        }
    }

    private function publishedTool(): Tool
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        return $tool;
    }

    private function publishedHtml(): string
    {
        foreach (['web-development' => 'Web Development', 'cms-content-platforms' => 'CMS & Content Platforms', 'digital-marketing' => 'Digital Marketing'] as $slug => $title) {
            Service::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'summary' => 'Service.', 'is_published' => true]);
        }
        $this->admin();
        $this->seed(ImageRequirementsToolSeeder::class);
        $this->seed(ImageKbOptimizerToolSeeder::class);
        $this->seed(UtmBuilderToolSeeder::class);
        $this->seededTool();
        foreach (Tool::query()->get() as $each) {
            $this->assertSame([], app(ToolPublisher::class)->publish($each, null), $each->slug);
        }

        return $this->get(self::URL)->assertOk()->getContent();
    }

    private function seededTool(): Tool
    {
        $this->seed(WebsiteSeoCheckerToolSeeder::class);

        return Tool::query()->where('slug', 'website-seo-pre-launch-checker')->sole();
    }
}
