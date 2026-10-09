<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\FetchResult;
use App\Tools\SeoChecker\Finding;
use App\Tools\SeoChecker\RobotsTxt;
use App\Tools\SeoChecker\StagingHosts;
use DOMDocument;

/**
 * Indexability & crawling: robots meta tags, X-Robots-Tag, robots.txt, the
 * XML sitemap (with a small sample of its URLs) and staging addresses left
 * in the page. These say whether search engines MAY crawl and index the
 * page — never whether Google actually has (only Search Console knows).
 */
class IndexabilityChecks extends CheckGroup
{
    public const CATEGORY = 'indexability';

    public const LABEL = 'Indexability & crawling';

    private const SITEMAP_MAX_BYTES = 5_000_000;

    private const SITEMAP_SAMPLE = 3;

    private const INDEXING_LIMITATION = 'This shows whether search engines are allowed to index the page, not whether Google has indexed it. Use Google Search Console\'s URL Inspection tool for that.';

    public function run(AuditContext $context): array
    {
        $robots = $this->robotsTxt($context);
        [$sitemap, $sitemapUrls] = $this->sitemap($context);

        return array_values(array_filter([
            $this->metaRobots($context),
            $this->xRobotsTag($context),
            $robots,
            $sitemap,
            $sitemapUrls,
            $this->stagingReferences($context),
        ]));
    }

    private function metaRobots(AuditContext $c): Finding
    {
        $tags = [];
        foreach (['robots', 'googlebot'] as $name) {
            foreach ($c->doc->meta($name) as $content) {
                $tags[] = ['name' => $name, 'directives' => $this->directives($content), 'raw' => $content];
            }
        }

        $why = 'A "noindex" robots meta tag tells search engines to keep the page out of search results. It is often left on by a staging setting such as WordPress\'s "Discourage search engines from indexing this site".';
        $evidence = array_map(fn (array $t): string => "<meta name=\"{$t['name']}\" content=\"{$t['raw']}\">", $tags);

        return $this->directiveFinding($c, 'index.meta_robots', 'robots meta tag', $tags, $evidence ?: ['No robots meta tag — indexing is allowed by default.'], $why,
            'Remove "noindex" from the robots meta tag. In WordPress, untick Settings → Reading → "Discourage search engines from indexing this site" and check the page\'s SEO plugin settings.');
    }

    private function xRobotsTag(AuditContext $c): Finding
    {
        $tags = [];
        foreach ($c->page->headerValues('x-robots-tag') as $value) {
            // "googlebot: noindex" applies to one crawler; a bare value to all.
            $agent = preg_match('/^\s*([a-z0-9_-]+)\s*:\s*(?!\s*\d)/i', $value, $m) && ! in_array(strtolower($m[1]), ['unavailable_after', 'max-snippet', 'max-image-preview', 'max-video-preview'], true) ? strtolower($m[1]) : 'all';
            $tags[] = ['name' => $agent, 'directives' => $this->directives($agent === 'all' ? $value : substr($value, strpos($value, ':') + 1)), 'raw' => $value];
        }

        $why = 'The X-Robots-Tag HTTP header works like the robots meta tag but is set by the server, CDN or a plugin, so it is easy to miss when looking at the page.';
        $evidence = array_map(fn (array $t): string => "X-Robots-Tag: {$t['raw']}", $tags);

        return $this->directiveFinding($c, 'index.x_robots_tag', 'X-Robots-Tag header', $tags, $evidence ?: ['No X-Robots-Tag header.'], $why,
            'Remove the noindex X-Robots-Tag header from your server configuration (.htaccess, nginx), CDN rules or the plugin that adds it.');
    }

    /**
     * @param  list<array{name: string, directives: list<string>, raw: string}>  $tags
     * @param  list<string>  $evidence
     */
    private function directiveFinding(AuditContext $c, string $id, string $source, array $tags, array $evidence, string $why, string $fix): Finding
    {
        $relevant = array_filter($tags, fn (array $t): bool => in_array($t['name'], ['all', 'robots', 'googlebot'], true));
        $directives = array_merge([], ...array_column($relevant, 'directives'));
        $noindex = in_array('noindex', $directives, true) || in_array('none', $directives, true);
        $nofollow = in_array('nofollow', $directives, true);

        if ($noindex && $c->isStaging()) {
            return $this->passed($id, "noindex set in the {$source} (expected on staging)", $evidence, $why,
                'Nothing to do on staging. Make sure it is removed on the production site at launch — this is one of the most common launch mistakes.',
                self::INDEXING_LIMITATION);
        }
        if ($noindex) {
            return $this->critical($id, "The {$source} blocks indexing (noindex)", $evidence, $why, $fix, self::INDEXING_LIMITATION);
        }
        if ($c->isStaging() && $id === 'index.meta_robots' && $c->page->headerValues('x-robots-tag') === []) {
            return $this->warning($id, 'This staging copy can be indexed', $evidence,
                'Staging copies that search engines can reach may get indexed and compete with the real site.',
                'Protect staging with a password (best) or add a noindex robots meta tag or X-Robots-Tag header until launch.');
        }
        if ($nofollow) {
            return $this->warning($id, "The {$source} sets nofollow", $evidence, 'nofollow asks search engines not to follow the links on this page, so pages linked only from here may not be discovered.', $fix);
        }

        return $this->passed($id, "The {$source} allows indexing", $evidence, $why, 'Nothing to do.', self::INDEXING_LIMITATION);
    }

    /**
     * @return list<string>
     */
    private function directives(string $content): array
    {
        return array_values(array_filter(array_map(fn (string $d): string => strtolower(trim($d)), explode(',', $content))));
    }

    private function robotsTxt(AuditContext $c): Finding
    {
        $url = $c->origin().'/robots.txt';
        $why = 'robots.txt tells crawlers which paths they may fetch. A leftover "Disallow: /" from staging stops search engines from crawling the whole site.';
        $limitation = 'Read the way Google documents it (most specific user-agent group, longest matching rule). Other crawlers may read the file differently.';
        $result = $c->fetch($url, ['max_redirects' => 5, 'max_bytes' => 500_000, 'accept' => 'text/plain,*/*;q=0.5']);

        if ($result instanceof AuditException) {
            return $this->notChecked('index.robots_txt', 'robots.txt', $this->failure($result), $why);
        }

        $status = $result->status;
        $evidence = ["{$url} → HTTP {$status}"];

        if ($status === 429 || $status >= 500) {
            return $this->finding('index.robots_txt', $this->blockerUnlessStaging($c), 'robots.txt returns a server error', $evidence,
                'When robots.txt returns a server error, Google stops crawling the site until it can read the file again.',
                'Make /robots.txt return HTTP 200 (or 404 if you don\'t need one).', $limitation);
        }

        if (! $result->successful()) {
            return $this->passed('index.robots_txt', 'No robots.txt restrictions', [...$evidence, 'Without a robots.txt file, crawlers may fetch every page.'], $why,
                'Optional: add a robots.txt that lists your sitemap.', $limitation);
        }

        $robots = new RobotsTxt($result->body);
        $c->shared['robots_sitemaps'] = $robots->sitemaps;

        if ($result->isHtml() && $robots->sitemaps === []) {
            return $this->warning('index.robots_txt', 'robots.txt returns an HTML page', [...$evidence, 'The file is HTML, not plain-text robots rules.'], $why,
                'Serve a plain-text robots.txt (or let it return 404). WordPress and most SEO plugins generate one automatically.', $limitation);
        }

        $path = (parse_url($c->finalUrl(), PHP_URL_PATH) ?: '/').(($q = parse_url($c->finalUrl(), PHP_URL_QUERY)) ? '?'.$q : '');
        $google = $robots->check('googlebot', $path);
        $everyone = $robots->check('*', $path);

        $describe = fn (string $who, array $r): string => "{$who}: ".($r['allowed'] ? 'allowed' : 'blocked').($r['rule'] ? " (rule \"{$r['rule']}\" in the \"{$r['group']}\" group)" : ' (no matching rule)');
        $evidence[] = $describe('Googlebot', $google);
        $evidence[] = $describe('Other crawlers (*)', $everyone);
        $fix = 'Remove the Disallow rule that matches this page (often "Disallow: /" left over from staging). In WordPress, also check Settings → Reading and your SEO plugin\'s robots.txt editor.';

        if (! $google['allowed']) {
            return $c->isStaging()
                ? $this->passed('index.robots_txt', 'robots.txt blocks crawling (expected on staging)', $evidence, $why,
                    'Nothing to do on staging. Replace this robots.txt at launch — a copied "Disallow: /" blocks the whole live site.', $limitation)
                : $this->critical('index.robots_txt', 'robots.txt blocks Googlebot from this page', $evidence, $why, $fix, $limitation);
        }

        if (! $everyone['allowed']) {
            return $this->warning('index.robots_txt', 'robots.txt blocks other crawlers', $evidence, $why,
                'If you want Bing and other search engines too, remove the matching Disallow rule from the "*" group.', $limitation);
        }

        return $this->passed('index.robots_txt', 'robots.txt allows crawling this page', $evidence, $why, 'Nothing to do.', $limitation);
    }

    /**
     * @return array{0: Finding, 1: ?Finding}
     */
    private function sitemap(AuditContext $c): array
    {
        $why = 'An XML sitemap lists the pages you want found, which helps search engines discover new and updated pages — especially on a new site with few links pointing to it.';
        $declared = array_slice(array_values(array_filter(array_map(fn (string $u): ?string => $c->absolute($u), $c->shared['robots_sitemaps'] ?? []))), 0, 2);
        $candidates = $declared ?: [$c->origin().'/sitemap.xml', $c->origin().'/sitemap_index.xml', $c->origin().'/wp-sitemap.xml'];
        $tried = [];

        foreach ($candidates as $url) {
            $result = $c->fetch($url, ['max_redirects' => 3, 'max_bytes' => self::SITEMAP_MAX_BYTES, 'accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.5']);

            if ($result instanceof AuditException) {
                $tried[] = "{$url}: ".$result->getMessage();

                continue;
            }

            $tried[] = "{$url} → HTTP {$result->status}";

            if (! $result->successful() || $result->isHtml()) {
                continue;
            }

            $parsed = $this->parseSitemap($result->body);
            $source = $declared !== [] ? 'listed in robots.txt' : 'found at the default location';

            if ($parsed === null) {
                return [$this->warning('index.sitemap', 'The sitemap isn\'t valid XML', ["{$result->finalUrl} ({$source}) → HTTP {$result->status}", 'The file could not be read as a sitemap.'], $why,
                    'Regenerate the sitemap with your CMS or SEO plugin and check it opens as XML in the browser.'), null];
            }

            return $this->sitemapFindings($c, $result, $parsed, $source, $why);
        }

        $none = $declared !== []
            ? $this->warning('index.sitemap', 'The sitemap in robots.txt doesn\'t load', $tried, $why, 'Fix the Sitemap: line in robots.txt so it points to a sitemap that loads, or regenerate the sitemap.')
            : $this->warning('index.sitemap', 'No XML sitemap found', ['No Sitemap: line in robots.txt.', ...$tried], $why,
                'Create an XML sitemap (WordPress 5.5+ has one at /wp-sitemap.xml; SEO plugins make their own), add a "Sitemap:" line to robots.txt, and submit it in Google Search Console.',
                'Only robots.txt and three common locations were checked.');

        return [$none, null];
    }

    /**
     * @param  array{type: string, locs: list<string>}  $parsed
     * @return array{0: Finding, 1: ?Finding}
     */
    private function sitemapFindings(AuditContext $c, FetchResult $result, array $parsed, string $source, string $why): array
    {
        $evidence = ["{$result->finalUrl} ({$source})"];
        $locs = $parsed['locs'];

        if ($parsed['type'] === 'sitemapindex') {
            $evidence[] = 'Sitemap index listing '.count($locs).' sitemap'.(count($locs) === 1 ? '' : 's').'.';
            $child = $locs === [] ? null : $c->fetch($locs[0], ['max_redirects' => 3, 'max_bytes' => self::SITEMAP_MAX_BYTES, 'accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.5']);
            $childParsed = $child instanceof FetchResult && $child->successful() ? $this->parseSitemap($child->body) : null;
            $locs = $childParsed !== null && $childParsed['type'] === 'urlset' ? $childParsed['locs'] : [];
            if ($locs !== []) {
                $evidence[] = "First sitemap ({$child->finalUrl}) lists ".count($locs).' URL'.(count($locs) === 1 ? '' : 's').'.';
            }
        } else {
            $evidence[] = 'Lists '.count($locs).' URL'.(count($locs) === 1 ? '' : 's').'.';
        }

        $problems = [];
        $otherHosts = array_values(array_filter($locs, fn (string $loc): bool => $c->host($loc) !== $c->host()));
        if ($otherHosts !== []) {
            $problems[] = count($otherHosts).' listed URL'.(count($otherHosts) === 1 ? ' uses' : 's use').' a different host, for example '.$otherHosts[0].(StagingHosts::looksLikeStaging($c->host($otherHosts[0])) ? ' (looks like a staging address)' : '').'.';
        }
        if ($parsed['type'] === 'urlset' && $locs === []) {
            $problems[] = 'The sitemap lists no URLs.';
        }

        $sitemap = $problems === []
            ? $this->passed('index.sitemap', 'XML sitemap found', $evidence, $why, 'Nothing to do. Submit it in Google Search Console if you haven\'t yet.')
            : $this->warning('index.sitemap', 'The XML sitemap needs attention', [...$evidence, ...$problems], $why,
                'Regenerate the sitemap after the final domain is set, so every URL uses the live https:// address.');

        return [$sitemap, $this->sampleSitemapUrls($c, $locs)];
    }

    /**
     * @param  list<string>  $locs
     */
    private function sampleSitemapUrls(AuditContext $c, array $locs): Finding
    {
        $why = 'A sitemap should list only final, working addresses (HTTP 200) — not redirects or errors.';
        $sample = array_slice(array_values(array_filter($locs, fn (string $loc): bool => preg_match('#^https?://#i', $loc) === 1)), 0, self::SITEMAP_SAMPLE);

        if ($sample === []) {
            return $this->notChecked('index.sitemap_urls', 'Sitemap URLs sample', 'No URLs to sample.', $why);
        }

        $evidence = [];
        $bad = 0;
        $checked = 0;
        foreach ($sample as $loc) {
            $result = $c->fetch($loc, ['method' => 'HEAD', 'max_redirects' => 0, 'timeout' => 5]);
            if ($result instanceof FetchResult && in_array($result->status, [405, 501], true)) {
                $result = $c->fetch($loc, ['max_redirects' => 0, 'max_bytes' => 262_144, 'timeout' => 5]);
            }
            if ($result instanceof AuditException) {
                $evidence[] = "{$loc}: ".$result->getMessage();

                continue;
            }
            $checked++;
            $evidence[] = "{$loc} → HTTP {$result->status}";
            $bad += $result->successful() ? 0 : 1;
        }

        $listed = in_array(true, array_map(fn (string $loc): bool => $c->sameUrl($loc, $c->finalUrl()), $locs), true);
        $evidence[] = $listed ? 'This page is listed in the sitemap that was read.' : 'This page was not found in the sitemap that was read.';
        $limitation = 'Only the first '.self::SITEMAP_SAMPLE.' URLs were requested; the rest were not checked.';

        if ($checked === 0) {
            return $this->notChecked('index.sitemap_urls', 'Sitemap URLs sample', $evidence, $why, 'Run the check again later.', $limitation);
        }

        return $bad > 0
            ? $this->warning('index.sitemap_urls', "{$bad} of {$checked} sampled sitemap URLs don't return HTTP 200", $evidence, $why,
                'Remove redirected, broken and noindex pages from the sitemap and list each page\'s final address.', $limitation)
            : $this->passed('index.sitemap_urls', 'Sampled sitemap URLs load', $evidence, $why, 'Nothing to do.', $limitation);
    }

    /**
     * @return array{type: string, locs: list<string>}|null
     */
    private function parseSitemap(string $xml): ?array
    {
        if (str_starts_with($xml, "\x1f\x8b")) {
            $xml = @gzdecode($xml, self::SITEMAP_MAX_BYTES) ?: '';
        }

        // Sitemaps never need a DTD; refusing one rules out entity tricks.
        if (trim($xml) === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return null;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $dom->documentElement : null;
        if ($root === null || ! in_array($root->localName, ['urlset', 'sitemapindex'], true)) {
            return null;
        }

        $locs = [];
        foreach ($dom->getElementsByTagName('loc') as $loc) {
            $value = trim($loc->textContent);
            if ($value !== '') {
                $locs[] = $value;
            }
            if (count($locs) >= 50_000) {
                break;
            }
        }

        return ['type' => $root->localName, 'locs' => $locs];
    }

    private function stagingReferences(AuditContext $c): Finding
    {
        $why = 'Links, images, canonicals and social tags that still point to a staging or temporary address break after launch, or send visitors and search engines to the wrong site.';
        $host = $c->host();
        $hits = [];

        foreach (array_unique($c->doc->absoluteUrls) as $url) {
            $urlHost = $c->host($url);
            $matches = $c->isStaging()
                ? $urlHost === $host && StagingHosts::looksLikeStaging($host)
                : $urlHost !== $host && $urlHost !== '' && StagingHosts::looksLikeStaging($urlHost);
            if ($matches) {
                $hits[] = $url;
            }
        }

        if ($hits === []) {
            return $this->passed('index.staging_references', 'No staging addresses found in the page', $c->isStaging()
                ? 'No absolute links to this staging host in the HTML.'
                : 'No absolute links to staging-looking hosts in the HTML.', $why, 'Nothing to do.', 'Based on hostnames that look like staging; only this page\'s HTML was read.');
        }

        $evidence = [count($hits).' reference'.(count($hits) === 1 ? '' : 's').' found, for example:', ...array_slice($hits, 0, 5)];

        return $this->warning('index.staging_references', $c->isStaging()
            ? 'Hard-coded staging addresses will need replacing at launch'
            : 'The page still links to a staging address', $evidence, $why,
            'Replace the staging address with the live domain everywhere (in WordPress, a search-and-replace on the database with a tool such as WP-CLI search-replace or Better Search Replace), then clear caches.',
            'Based on hostnames that look like staging; only this page\'s HTML was read.');
    }
}
