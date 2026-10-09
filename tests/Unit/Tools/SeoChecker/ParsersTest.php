<?php

namespace Tests\Unit\Tools\SeoChecker;

use App\Tools\SeoChecker\Finding;
use App\Tools\SeoChecker\PageDocument;
use App\Tools\SeoChecker\RobotsTxt;
use App\Tools\SeoChecker\Scorer;
use App\Tools\SeoChecker\StagingHosts;
use PHPUnit\Framework\TestCase;

/**
 * robots.txt matching, staging-host heuristics, HTML extraction and the
 * deterministic score.
 */
class ParsersTest extends TestCase
{
    public function test_robots_txt_uses_the_most_specific_group_and_longest_rule(): void
    {
        $robots = new RobotsTxt(<<<'TXT'
            # comment
            User-agent: *
            Disallow: /
            Allow: /public/

            User-agent: Googlebot
            User-agent: bingbot
            Disallow: /private
            Allow: /private/ok$
            Disallow: /*.pdf$

            Sitemap: https://example.com/sitemap.xml
            TXT);

        $this->assertSame(['https://example.com/sitemap.xml'], $robots->sitemaps);

        $this->assertTrue($robots->check('googlebot', '/')['allowed'], 'Googlebot ignores the * group');
        $this->assertFalse($robots->check('googlebot', '/private/page')['allowed']);
        $this->assertTrue($robots->check('googlebot', '/private/ok')['allowed']);
        $this->assertFalse($robots->check('googlebot', '/private/ok/more')['allowed']);
        $this->assertFalse($robots->check('googlebot', '/files/a.pdf')['allowed']);
        $this->assertTrue($robots->check('googlebot', '/files/a.pdf?x=1')['allowed']);

        $this->assertFalse($robots->check('*', '/about')['allowed']);
        $this->assertTrue($robots->check('*', '/public/x')['allowed']);
        $this->assertSame('Disallow: /', $robots->check('*', '/about')['rule']);
    }

    public function test_robots_txt_allow_wins_a_tie_and_empty_disallow_allows_everything(): void
    {
        $this->assertTrue((new RobotsTxt("User-agent: *\nDisallow: /page\nAllow: /page"))->check('googlebot', '/page')['allowed']);
        $this->assertTrue((new RobotsTxt("User-agent: *\nDisallow:"))->check('googlebot', '/anything')['allowed']);
        $this->assertTrue((new RobotsTxt(''))->check('googlebot', '/')['allowed']);
        $this->assertTrue((new RobotsTxt('<html><body>Not found</body></html>'))->check('googlebot', '/')['allowed']);
    }

    public function test_staging_host_heuristics(): void
    {
        foreach (['staging.example.com', 'dev.example.co.uk', 'stage2.example.com', 'uat-shop.example.com', 'mysite.netlify.app', 'client.wpenginepowered.com', 'preview.example.org', 'abc.ngrok-free.app'] as $host) {
            $this->assertTrue(StagingHosts::looksLikeStaging($host), $host);
        }
        foreach (['example.com', 'www.example.com', 'devon-bakery.com', 'testimonials.example.com', 'shop.example.com', 'developer.mozilla.org'] as $host) {
            $this->assertFalse(StagingHosts::looksLikeStaging($host), $host);
        }
    }

    public function test_page_document_extracts_metadata_without_running_anything(): void
    {
        $doc = new PageDocument(<<<'HTML'
            <!doctype html><html lang="en-GB"><head>
            <meta charset="utf-8"><title> My  Page </title>
            <meta name="Description" content="First">
            <meta property="og:title" content="OG title">
            <link rel="canonical" href="/canonical">
            <link rel="shortcut icon" href="/favicon.png">
            <base href="https://cdn.example.com/">
            <script type="application/ld+json">{"@type":"Organization"}</script>
            <script>document.title = 'changed by js'</script>
            </head><body>
            <svg><title>icon title</title></svg>
            <h1>Main</h1><h3>Skipped</h3>
            <img src="a.png"><img src="b.png" alt=""><img src="c.png" alt="Chart">
            <a href="/x" rel="nofollow ugc">x</a><a href="https://staging.example.com/y">y</a>
            <p>one two three</p>
            </body></html>
            HTML);

        $this->assertSame(['My Page'], $doc->titles, 'svg titles ignored, whitespace collapsed, script not run');
        $this->assertSame('en-GB', $doc->lang);
        $this->assertSame('utf-8', $doc->charset());
        $this->assertSame(['First'], $doc->meta('description'));
        $this->assertSame('OG title', $doc->firstMeta('og:title'));
        $this->assertSame(['/canonical'], $doc->linkHrefs('canonical'));
        $this->assertSame(['/favicon.png'], $doc->linkHrefs('icon'));
        $this->assertSame('https://cdn.example.com/', $doc->baseHref);
        $this->assertSame([['level' => 1, 'text' => 'Main'], ['level' => 3, 'text' => 'Skipped']], $doc->headings);
        $this->assertSame([null, '', 'Chart'], array_column($doc->images, 'alt'));
        $this->assertSame(['nofollow', 'ugc'], $doc->anchors[0]['rel']);
        $this->assertCount(1, $doc->jsonLd);
        $this->assertContains('https://staging.example.com/y', $doc->absoluteUrls);
        $this->assertGreaterThanOrEqual(5, $doc->wordCount);
    }

    public function test_clean_strips_control_and_bidi_characters_and_bounds_length(): void
    {
        $this->assertSame('a b c', PageDocument::clean("a\x00\nb\u{202E}c"));
        $this->assertSame(10, mb_strlen(PageDocument::clean(str_repeat('x', 50), 10)));
    }

    public function test_the_score_is_deterministic_and_blockers_cap_it(): void
    {
        $scorer = new Scorer;
        $f = fn (string $id, string $severity): Finding => new Finding($id, 'x', $severity, 't', [], 'w', 'f');

        // weights: meta.title 5, meta.description 3, perf.page_speed 0 (and not checked anyway)
        $this->assertSame(100, $scorer->score([$f('meta.title', Finding::PASSED), $f('meta.description', Finding::PASSED), $f('perf.page_speed', Finding::NOT_CHECKED)]));
        $this->assertSame(81, $scorer->score([$f('meta.title', Finding::PASSED), $f('meta.description', Finding::WARNING)]), '(5 + 1.5) / 8');
        $this->assertSame(Scorer::BLOCKER_CAP, $scorer->score([
            $f('index.meta_robots', Finding::CRITICAL),
            ...array_map(fn (string $id): Finding => $f($id, Finding::PASSED), array_keys(Scorer::WEIGHTS)),
        ]), 'a blocker caps an otherwise near-perfect score');
        $this->assertNull($scorer->score([$f('perf.page_speed', Finding::NOT_CHECKED)]), 'nothing checked → no score, not zero');

        $this->assertSame('blocked', $scorer->readiness([Finding::CRITICAL => 1, Finding::WARNING => 0], true)['level']);
        $this->assertSame('review', $scorer->readiness([Finding::CRITICAL => 0, Finding::WARNING => 2], true)['level']);
        $this->assertSame('ready', $scorer->readiness([Finding::CRITICAL => 0, Finding::WARNING => 0], true)['level']);
        $this->assertSame('incomplete', $scorer->readiness([Finding::CRITICAL => 0, Finding::WARNING => 0], false)['level']);
    }
}
