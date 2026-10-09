<?php

namespace Tests\Feature;

use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Auditor;
use App\Tools\SeoChecker\HostResolver;
use App\Tools\SeoChecker\SafeFetcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The audit engine against mocked websites (Http::fake + a fixed DNS map):
 * every check's critical/warning/passed paths, redirects and their SSRF
 * re-validation, failures that must not become a zero score, and limits.
 * No test touches the real network or real DNS.
 */
class WebsiteSeoCheckerAuditTest extends TestCase
{
    private const SITE = 'https://example.com/';

    /** @var array<string, list<string>> */
    private array $dns = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new class(fn (string $host): array => $this->dns[$host] ?? ['93.184.216.34']) implements HostResolver
        {
            public function __construct(private readonly \Closure $lookup) {}

            public function resolve(string $host): array
            {
                return ($this->lookup)($host);
            }
        });
    }

    public function test_a_well_configured_page_passes_and_is_scored(): void
    {
        $report = $this->audit($this->site());

        $this->assertSame('complete', $report['status']);
        $this->assertSame(self::SITE, $report['final_url']);
        $this->assertSame(0, $report['counts']['critical'], $this->titles($report, 'critical'));
        $this->assertSame(0, $report['counts']['warning'], $this->titles($report, 'warning'));
        $this->assertSame(100, $report['score']);
        $this->assertSame('ready', $report['readiness']['level']);
        $this->assertSame('not_checked', $this->finding($report, 'perf.page_speed')['severity'], 'Core Web Vitals are never claimed');
        $this->assertSame('Good title', $report['preview']['title']);
        $this->assertSame('https://example.com/share.png', $report['preview']['image']);

        foreach ($report['findings'] as $finding) {
            foreach (['id', 'category', 'severity', 'title', 'evidence', 'why', 'fix'] as $key) {
                $this->assertNotEmpty($finding[$key], "{$finding['id']} has {$key}");
            }
        }
    }

    public function test_noindex_meta_header_and_robots_block_are_launch_blockers_on_a_live_site(): void
    {
        $report = $this->audit($this->site(
            head: '<meta name="robots" content="noindex, nofollow">',
            headers: ['X-Robots-Tag' => 'noindex'],
            robots: "User-agent: *\nDisallow: /",
        ));

        $this->assertSame('critical', $this->finding($report, 'index.meta_robots')['severity']);
        $this->assertSame('critical', $this->finding($report, 'index.x_robots_tag')['severity']);
        $this->assertSame('critical', $this->finding($report, 'index.robots_txt')['severity']);
        $this->assertSame('blocked', $report['readiness']['level']);
        $this->assertLessThanOrEqual(59, $report['score'], 'blockers cap the score');
        $this->assertSame('critical', $report['findings'][0]['severity'], 'blockers are listed first');
        $this->assertStringContainsString('not whether Google has indexed', $this->finding($report, 'index.meta_robots')['limitation']);
    }

    public function test_the_same_settings_are_expected_on_a_staging_copy(): void
    {
        $report = $this->audit($this->site(
            head: '<meta name="robots" content="noindex">',
            robots: "User-agent: *\nDisallow: /",
        ), 'staging');

        $this->assertSame('passed', $this->finding($report, 'index.meta_robots')['severity']);
        $this->assertSame('passed', $this->finding($report, 'index.robots_txt')['severity']);
        $this->assertStringContainsString('at launch', $this->finding($report, 'index.meta_robots')['fix']);
    }

    public function test_googlebot_specific_header_and_meta_are_read(): void
    {
        $report = $this->audit($this->site(
            head: '<meta name="googlebot" content="noindex">',
            headers: ['X-Robots-Tag' => 'otherbot: noindex'],
        ));

        $this->assertSame('critical', $this->finding($report, 'index.meta_robots')['severity']);
        $this->assertSame('passed', $this->finding($report, 'index.x_robots_tag')['severity'], 'a header for another crawler is not a Google block');
    }

    public function test_missing_and_duplicate_metadata(): void
    {
        $missing = $this->audit($this->site(title: null, description: null, canonical: null, extraHead: ''));
        $this->assertSame('critical', $this->finding($missing, 'meta.title')['severity']);
        $this->assertSame('warning', $this->finding($missing, 'meta.description')['severity']);
        $this->assertSame('warning', $this->finding($missing, 'meta.canonical')['severity']);
        $this->assertNull($this->finding($missing, 'meta.canonical_target'));

        $duplicate = $this->audit($this->site(
            title: 'Home',
            description: 'Home',
            head: '<title>Second</title><meta name="description" content="Another"><link rel="canonical" href="https://example.com/other">',
        ));
        $title = $this->finding($duplicate, 'meta.title');
        $this->assertSame('warning', $title['severity']);
        $this->assertStringContainsString('2 <title> elements', implode(' ', $title['evidence']));
        $this->assertStringContainsString('default or placeholder', implode(' ', $title['evidence']));
        $this->assertStringContainsString('2 meta descriptions', implode(' ', $this->finding($duplicate, 'meta.description')['evidence']));
        $this->assertSame('More than one canonical URL', $this->finding($duplicate, 'meta.canonical')['title']);
    }

    public function test_canonical_mismatch_staging_and_broken_target(): void
    {
        $other = $this->audit($this->site(canonical: 'https://example.com/other'), routes: ['https://example.com/other' => Http::response('gone', 404)]);
        $this->assertSame('warning', $this->finding($other, 'meta.canonical')['severity']);
        $this->assertSame('critical', $this->finding($other, 'meta.canonical_target')['severity']);

        $staging = $this->audit($this->site(canonical: 'https://staging.example.com/'), routes: ['https://staging.example.com/' => Http::response('ok', 200)]);
        $this->assertSame('critical', $this->finding($staging, 'meta.canonical')['severity']);
        $this->assertSame('warning', $this->finding($staging, 'index.staging_references')['severity']);

        $relative = $this->audit($this->site(canonical: '/'));
        $this->assertSame('passed', $this->finding($relative, 'meta.canonical')['severity']);
        $this->assertStringContainsString('relative', implode(' ', $this->finding($relative, 'meta.canonical')['evidence']));

        $redirecting = $this->audit($this->site(canonical: 'https://example.com/old'), routes: ['https://example.com/old' => Http::response('', 301, ['Location' => '/'])]);
        $this->assertSame('warning', $this->finding($redirecting, 'meta.canonical_target')['severity']);
    }

    public function test_redirects_are_followed_and_reported(): void
    {
        $report = $this->audit(null, url: 'http://example.com/', routes: [
            'http://example.com/' => Http::response('', 302, ['Location' => 'https://www.example.com/start']),
            'https://www.example.com/start' => Http::response('', 301, ['Location' => '/']),
            'https://www.example.com/' => $this->html($this->page(canonical: 'https://www.example.com/')),
        ]);

        $this->assertSame('https://www.example.com/', $report['final_url']);
        $this->assertCount(2, $report['http']['redirects']);
        $redirects = $this->finding($report, 'http.redirects');
        $this->assertSame('warning', $redirects['severity']);
        $this->assertSame('Redirect chain of 2 steps', $redirects['title']);
    }

    public function test_redirect_loops_and_too_many_redirects_are_unavailable_not_zero(): void
    {
        $loop = $this->audit(null, routes: [
            self::SITE => Http::response('', 301, ['Location' => 'https://example.com/a']),
            'https://example.com/a' => Http::response('', 301, ['Location' => self::SITE]),
        ]);
        $this->assertSame('unavailable', $loop['status']);
        $this->assertSame('redirect_loop', $loop['error']['code']);
        $this->assertNull($loop['score']);

        $routes = [];
        for ($i = 0; $i < 8; $i++) {
            $routes[$i === 0 ? self::SITE : "https://example.com/{$i}"] = Http::response('', 302, ['Location' => '/'.($i + 1)]);
        }
        $this->assertSame('too_many_redirects', $this->audit(null, routes: $routes)['error']['code']);
    }

    public function test_redirects_into_private_networks_or_other_schemes_are_refused(): void
    {
        $this->dns['internal.example.net'] = ['10.0.0.8'];

        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data/', 'http://internal.example.net/', 'file:///etc/passwd', 'http://[::1]/'] as $target) {
            $requested = [];
            $report = $this->audit(null, routes: [
                self::SITE => Http::response('', 302, ['Location' => $target]),
                '*' => function (Request $request) use (&$requested) {
                    $requested[] = $request->url();

                    return Http::response('internal', 200);
                },
            ]);

            $this->assertSame('redirect_blocked', $report['error']['code'], $target);
            $this->assertSame([], $requested, "{$target} was never requested");
            $this->assertStringNotContainsString('10.0.0.8', json_encode($report));
        }
    }

    public function test_a_hostname_resolving_privately_is_blocked_before_any_request(): void
    {
        $this->dns['example.com'] = ['127.0.0.1'];
        $report = $this->audit(null, routes: ['*' => fn () => $this->fail('no request may be sent')]);

        $this->assertSame('unavailable', $report['status']);
        $this->assertSame('blocked_destination', $report['error']['code']);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($report));
    }

    public function test_follow_up_requests_are_guarded_too(): void
    {
        $this->dns['img.example.org'] = ['192.168.1.20'];
        $report = $this->audit($this->site(head: '<meta property="og:image" content="https://img.example.org/share.png">'));

        $image = $this->finding($report, 'social.og_image');
        $this->assertSame('not_checked', $image['severity']);
        $this->assertStringContainsString('private', implode(' ', $image['evidence']));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'img.example.org'));
    }

    public function test_timeouts_connection_failures_and_tls_errors_are_unavailable(): void
    {
        foreach ([
            'timeout' => 'cURL error 28: Operation timed out after 12001 milliseconds',
            'connect_failed' => 'cURL error 7: Failed to connect',
            'tls_failed' => 'cURL error 60: SSL certificate problem: certificate has expired',
        ] as $code => $message) {
            $report = $this->audit(null, routes: [self::SITE => Http::failedConnection($message)]);
            $this->assertSame('unavailable', $report['status']);
            $this->assertSame($code, $report['error']['code']);
            $this->assertNull($report['score']);
            $this->assertStringNotContainsString('cURL', $report['error']['message'], 'raw transport errors are never shown');
        }
    }

    public function test_oversized_and_non_html_responses_are_unavailable(): void
    {
        $big = $this->audit(null, routes: [self::SITE => Http::response(str_repeat('a', Auditor::PAGE_MAX_BYTES + 1), 200, ['Content-Type' => 'text/html'])]);
        $this->assertSame('too_large', $big['error']['code']);

        $pdf = $this->audit(null, routes: [self::SITE => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);
        $this->assertSame('not_html', $pdf['error']['code']);
        $this->assertStringContainsString('application/pdf', $pdf['error']['message']);
    }

    public function test_gzip_bodies_are_decoded_within_the_limit(): void
    {
        $fetcher = app(SafeFetcher::class);
        $this->fakeHttp([
            'https://example.com/small' => Http::response(gzencode('<html>hello</html>'), 200, ['Content-Encoding' => 'gzip']),
            'https://example.com/bomb' => Http::response(gzencode(str_repeat('0', 5_000_000)), 200, ['Content-Encoding' => 'gzip']),
            'https://example.com/br' => Http::response('xx', 200, ['Content-Encoding' => 'br']),
        ]);

        $this->assertSame('<html>hello</html>', $fetcher->fetch('https://example.com/small')->body);

        foreach (['bomb' => 'too_large', 'br' => 'decode_failed'] as $path => $reason) {
            try {
                $fetcher->fetch("https://example.com/{$path}", ['max_bytes' => 1_000_000]);
                $this->fail("{$path} was accepted");
            } catch (AuditException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_error_status_pages_are_blockers_without_a_score(): void
    {
        $report = $this->audit(null, routes: [self::SITE => Http::response('<html><title>Not found</title></html>', 404, ['Content-Type' => 'text/html'])]);

        $this->assertSame('complete', $report['status']);
        $this->assertFalse($report['analysed']);
        $this->assertNull($report['score']);
        $this->assertSame('critical', $this->finding($report, 'http.status')['severity']);
        $this->assertNull($this->finding($report, 'meta.title'), 'page checks are skipped');

        $protected = $this->audit(null, routes: [self::SITE => Http::response('auth', 401)], intent: 'staging');
        $this->assertSame('passed', $this->finding($protected, 'http.status')['severity']);
        $this->assertSame('incomplete', $protected['readiness']['level']);
    }

    public function test_http_to_https_redirect_check(): void
    {
        $missing = $this->audit($this->site(), routes: ['http://example.com/' => $this->html($this->page())]);
        $this->assertSame('warning', $this->finding($missing, 'http.https_redirect')['severity']);

        $temporary = $this->audit($this->site(), routes: ['http://example.com/' => Http::response('', 302, ['Location' => self::SITE])]);
        $this->assertSame('warning', $this->finding($temporary, 'http.https_redirect')['severity']);

        $plain = $this->audit(null, url: 'http://example.com/', routes: ['http://example.com/' => $this->html($this->page(canonical: 'http://example.com/'))]);
        $this->assertSame('critical', $this->finding($plain, 'http.https')['severity']);
    }

    public function test_robots_txt_server_errors_and_missing_files(): void
    {
        $error = $this->audit($this->site(), routes: ['https://example.com/robots.txt' => Http::response('', 503)]);
        $this->assertSame('critical', $this->finding($error, 'index.robots_txt')['severity']);

        $missing = $this->audit($this->site(), routes: ['https://example.com/robots.txt' => Http::response('', 404)]);
        $this->assertSame('passed', $this->finding($missing, 'index.robots_txt')['severity']);

        $googleOnly = $this->audit($this->site(robots: "User-agent: Googlebot\nAllow: /\n\nUser-agent: *\nDisallow: /"));
        $this->assertSame('warning', $this->finding($googleOnly, 'index.robots_txt')['severity']);
    }

    public function test_sitemap_parsing_index_malformed_and_missing(): void
    {
        $index = $this->audit($this->site(sitemap: '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.com/pages.xml</loc></sitemap></sitemapindex>'), routes: [
            'https://example.com/pages.xml' => Http::response($this->urlset(['https://example.com/', 'https://example.com/gone', 'https://staging.example.com/x']), 200, ['Content-Type' => 'application/xml']),
            'https://example.com/gone' => Http::response('', 404),
            'https://staging.example.com/x' => Http::response('', 200),
        ]);
        $sitemap = $this->finding($index, 'index.sitemap');
        $this->assertSame('warning', $sitemap['severity'], 'a staging host is listed');
        $this->assertStringContainsString('Sitemap index listing 1 sitemap', implode(' ', $sitemap['evidence']));
        $this->assertStringContainsString('staging', implode(' ', $sitemap['evidence']));
        $sample = $this->finding($index, 'index.sitemap_urls');
        $this->assertSame('warning', $sample['severity']);
        $this->assertStringContainsString('This page is listed', implode(' ', $sample['evidence']));

        $broken = $this->audit($this->site(sitemap: '<urlset><url><loc>https://example.com/</loc></url'));
        $this->assertSame('The sitemap isn\'t valid XML', $this->finding($broken, 'index.sitemap')['title']);

        $entity = $this->audit($this->site(sitemap: '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><urlset><url><loc>&e;</loc></url></urlset>'));
        $this->assertSame('The sitemap isn\'t valid XML', $this->finding($entity, 'index.sitemap')['title']);
        $this->assertStringNotContainsString('root:', json_encode($entity));

        $none = $this->audit($this->site(robots: "User-agent: *\nAllow: /", sitemap: null));
        $this->assertSame('No XML sitemap found', $this->finding($none, 'index.sitemap')['title']);
    }

    public function test_open_graph_and_twitter_cards(): void
    {
        $bare = $this->audit($this->site(extraHead: ''));
        foreach (['social.og_title', 'social.og_description', 'social.og_url', 'social.og_image', 'social.twitter_card'] as $id) {
            $this->assertSame('warning', $this->finding($bare, $id)['severity'], $id);
        }
        $this->assertNull($bare['preview']['image'], 'no fabricated image');
        $this->assertSame('Example Studio – Websites That Launch Well', $bare['preview']['title'], 'falls back to the real <title>');

        $small = $this->audit($this->site(), routes: ['https://example.com/share.png' => Http::response($this->png(200, 100), 200, ['Content-Type' => 'image/png'])]);
        $this->assertSame('warning', $this->finding($small, 'social.og_image')['severity']);
        $this->assertStringContainsString('200×100', implode(' ', $this->finding($small, 'social.og_image')['evidence']));

        $notImage = $this->audit($this->site(), routes: ['https://example.com/share.png' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->assertSame('warning', $this->finding($notImage, 'social.og_image')['severity']);
    }

    public function test_structure_checks(): void
    {
        $report = $this->audit($this->site(body: <<<'HTML'
            <h2>No h1 here</h2><h4>Skipped a level</h4>
            <img src="/a.png"><img src="/b.png" alt="">
            <a href="#">Menu</a><a href="javascript:void(0)">JS</a>
            <script type="application/ld+json">{"@type": "Organization",}</script>
            <p>Short.</p>
            HTML));

        $this->assertSame('No H1 heading', $this->finding($report, 'structure.h1')['title']);
        $this->assertSame('warning', $this->finding($report, 'structure.heading_order')['severity']);
        $this->assertSame('1 image without alt text', $this->finding($report, 'structure.image_alt')['title']);
        $this->assertSame('warning', $this->finding($report, 'structure.links')['severity']);
        $this->assertSame('Structured data contains invalid JSON', $this->finding($report, 'structure.structured_data')['title']);
        $this->assertSame('warning', $this->finding($report, 'structure.content')['severity']);

        $multi = $this->audit($this->site(body: '<h1>One</h1><h1>Two</h1>'.$this->words()));
        $h1 = $this->finding($multi, 'structure.h1');
        $this->assertSame('2 H1 headings', $h1['title']);
        $this->assertStringContainsString('not a Google penalty', $h1['limitation']);
    }

    public function test_link_sample_reports_broken_links_and_bounds_requests(): void
    {
        $links = implode('', array_map(fn (int $i): string => "<a href=\"/p{$i}\">p{$i}</a>", range(1, 30)));
        $routes = ['https://example.com/p1' => Http::response('', 404), 'https://example.com/p2' => Http::response('', 403), 'https://example.com/p3' => Http::response('', 200), 'https://example.com/p4' => Http::response('', 301, ['Location' => '/']), 'https://example.com/p5' => Http::response('', 200)];
        $report = $this->audit($this->site(body: "<h1>Links</h1>{$links}".$this->words()), routes: $routes);

        $sample = $this->finding($report, 'structure.link_sample');
        $this->assertSame('1 broken link in the sample', $sample['title']);
        $this->assertStringContainsString('unverified', implode(' ', $sample['evidence']));
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/p6'));
        $this->assertLessThanOrEqual(25, $report['requests']);
    }

    public function test_remote_text_is_cleaned_and_returned_as_plain_data(): void
    {
        $report = $this->audit($this->site(title: "<script>alert(1)</script>\u{202E}evil", body: '<h1><img src=x onerror=alert(1)>Hi</h1>'.$this->words()));

        $title = $this->finding($report, 'meta.title');
        $this->assertStringContainsString('<script>alert(1)</script> evil', $title['evidence'][0], 'kept as text, bidi control removed');
        $this->assertStringNotContainsString("\u{202E}", json_encode($report, JSON_UNESCAPED_UNICODE));
        $this->assertSame('H1: "Hi"', $this->finding($report, 'structure.h1')['evidence'][0]);
    }

    public function test_the_wordpress_platform_is_detected_for_the_cta(): void
    {
        $this->assertSame('wordpress', $this->audit($this->site(body: '<h1>WP</h1><img src="/wp-content/uploads/a.jpg" alt="a">'.$this->words()))['platform']);
        $this->assertNull($this->audit($this->site())['platform']);
    }

    // ------------------------------------------------------------ helpers

    /**
     * @param  array<string, mixed>|null  $site  routes for a normal site (see site())
     * @param  array<string, mixed>  $routes  overrides, matched first
     * @return array<string, mixed>
     */
    private function audit(?array $site, string $intent = 'live', ?string $url = null, array $routes = []): array
    {
        $this->fakeHttp([...array_merge($site ?? [], $routes), '*' => Http::response('', 404)]);

        return app(Auditor::class)->audit($url ?? self::SITE, $intent);
    }

    /**
     * Routes for a healthy site, with parts overridable.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function site(
        ?string $title = 'Example Studio – Websites That Launch Well',
        ?string $description = 'A clear description of what this page offers, long enough to be useful in results.',
        ?string $canonical = self::SITE,
        string $head = '',
        ?string $extraHead = null,
        ?string $body = null,
        array $headers = [],
        string $robots = "User-agent: *\nAllow: /\nSitemap: https://example.com/sitemap.xml",
        ?string $sitemap = 'default',
    ): array {
        $sitemap = $sitemap === 'default' ? $this->urlset([self::SITE]) : $sitemap;

        return array_filter([
            self::SITE => $this->html($this->page($title, $description, $canonical, $head, $extraHead, $body), $headers),
            'http://example.com/' => Http::response('', 301, ['Location' => self::SITE]),
            'https://example.com/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            'https://example.com/sitemap.xml' => $sitemap === null ? null : Http::response($sitemap, 200, ['Content-Type' => 'application/xml']),
            'https://example.com/share.png' => Http::response($this->png(1200, 630), 200, ['Content-Type' => 'image/png']),
            'https://example.com/favicon.png' => Http::response($this->png(48, 48), 200, ['Content-Type' => 'image/png']),
            'https://example.com/about' => Http::response('', 200),
        ]);
    }

    private function page(
        ?string $title = 'Example Studio – Websites That Launch Well',
        ?string $description = 'A clear description of what this page offers, long enough to be useful in results.',
        ?string $canonical = self::SITE,
        string $head = '',
        ?string $extraHead = null,
        ?string $body = null,
    ): string {
        $e = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);
        $extraHead ??= '<meta property="og:title" content="Good title"><meta property="og:description" content="Shared description">'
            .'<meta property="og:url" content="'.$e($canonical ?? self::SITE).'"><meta property="og:image" content="https://example.com/share.png">'
            .'<meta name="twitter:card" content="summary_large_image"><link rel="icon" href="/favicon.png">'
            .'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Example"}</script>';
        $body ??= '<h1>Welcome</h1><h2>Section</h2><img src="/hero.jpg" alt="Hero"><a href="/about">About</a>'.$this->words();

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .($title !== null ? '<title>'.$e($title).'</title>' : '')
            .($description !== null ? '<meta name="description" content="'.$e($description).'">' : '')
            .($canonical !== null ? '<link rel="canonical" href="'.$e($canonical).'">' : '')
            .$head.$extraHead.'</head><body>'.$body.'</body></html>';
    }

    /**
     * A fresh fake per audit: Http::fake() otherwise keeps earlier stubs,
     * which would shadow this audit's routes. Stray requests always fail.
     *
     * @param  array<string, mixed>  $routes
     */
    private function fakeHttp(array $routes): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($routes);
    }

    private function html(string $html, array $headers = [])
    {
        return Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', ...$headers]);
    }

    private function urlset(array $locs): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', array_map(fn (string $loc): string => "<url><loc>{$loc}</loc></url>", $locs)).'</urlset>';
    }

    private function words(): string
    {
        return '<p>'.str_repeat('Useful words about the page and what it offers to visitors. ', 12).'</p>';
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function finding(array $report, string $id): ?array
    {
        foreach ($report['findings'] ?? [] as $finding) {
            if ($finding['id'] === $id) {
                return $finding;
            }
        }

        return null;
    }

    private function titles(array $report, string $severity): string
    {
        return implode('; ', array_map(fn (array $f): string => "{$f['id']}: {$f['title']} — ".implode(' / ', $f['evidence']), array_filter($report['findings'], fn (array $f): bool => $f['severity'] === $severity)));
    }
}
