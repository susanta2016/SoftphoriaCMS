<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Finding;
use App\Tools\SeoChecker\StagingHosts;

/**
 * Metadata: title, meta description, canonical (and whether it loads),
 * language, character encoding, viewport and favicon.
 */
class MetadataChecks extends CheckGroup
{
    public const CATEGORY = 'metadata';

    public const LABEL = 'Metadata';

    /** Exact titles/descriptions that are a theme, framework or server default. */
    private const DEFAULT_TEXT = '/^(?:home|homepage|untitled|untitled document|document|index|new page|page title|title|site title|website|my site|my website|my blog|my wordpress (?:site|website|blog)|just another wordpress site|another wordpress site|react app|vite app|vite \+ \w+(?: \+ \w+)?|create next app|nuxt app|angular app|welcome to nginx!?|it works!?|apache2? \w+ default page.*|coming soon|under construction|hello world!?|test|test page|lorem ipsum.*)$/i';

    public function run(AuditContext $context): array
    {
        $canonical = $this->canonicalUrls($context);
        $context->shared['canonical'] = $canonical[0] ?? null;

        return array_values(array_filter([
            $this->title($context),
            $this->description($context),
            $this->canonical($context, $canonical),
            $this->canonicalTarget($context, $canonical[0] ?? null),
            $this->lang($context),
            $this->charset($context),
            $this->viewport($context),
            $this->favicon($context),
        ]));
    }

    private function title(AuditContext $c): Finding
    {
        $titles = $c->doc->titles;
        $filled = array_values(array_filter($titles, fn (string $t): bool => $t !== ''));
        $why = 'The title is usually the clickable headline in search results and the name in browser tabs and bookmarks.';
        $fix = 'Write a unique, specific title for each page — what the page offers, then your brand. In WordPress set it with your SEO plugin (for example Yoast or Rank Math) or in the page settings.';

        if ($filled === []) {
            return $this->critical('meta.title', 'The page title is missing', $titles === [] ? 'No <title> element in the HTML.' : 'The <title> element is empty.', $why, $fix);
        }

        $title = $filled[0];
        $length = mb_strlen($title);
        $evidence = ["Title: {$this->quote($title, 200)} ({$length} characters)"];
        $problems = [];

        if (count($titles) > 1) {
            $problems[] = 'The page has '.count($titles).' <title> elements; only one is used.';
        }
        if (preg_match(self::DEFAULT_TEXT, $title)) {
            $problems[] = 'This looks like a default or placeholder title.';
        }
        if ($length < 15) {
            $problems[] = 'Very short — probably not descriptive enough.';
        } elseif ($length > 60) {
            $problems[] = 'Longer than about 60 characters, so search results may shorten it (the cut-off depends on pixel width, not a fixed count).';
        }

        if ($problems !== []) {
            return $this->warning('meta.title', 'The page title needs attention', [...$evidence, ...$problems], $why, $fix,
                'Length guidance is a display guideline, not a Google ranking rule.');
        }

        return $this->passed('meta.title', 'The page has a title', $evidence, $why);
    }

    private function description(AuditContext $c): Finding
    {
        $values = $c->doc->meta('description');
        $why = 'Search engines often use the meta description as the snippet under your title, so it shapes whether people click. It is not a direct ranking factor, and Google may write its own snippet.';
        $fix = 'Write a unique summary of the page (roughly 70–160 characters) that tells people what they will get. In WordPress, set it in your SEO plugin.';
        $description = trim($values[0] ?? '');

        if ($description === '') {
            return $this->warning('meta.description', 'No meta description', $values === [] ? 'No <meta name="description"> in the HTML.' : 'The meta description is empty.', $why, $fix);
        }

        $length = mb_strlen($description);
        $evidence = ["Description: {$this->quote($description, 300)} ({$length} characters)"];
        $problems = [];

        if (count($values) > 1) {
            $problems[] = 'The page has '.count($values).' meta descriptions; keep one.';
        }
        if (preg_match(self::DEFAULT_TEXT, $description) || stripos($description, 'lorem ipsum') !== false) {
            $problems[] = 'This looks like a default or placeholder description.';
        }
        if (mb_strtolower($description) === mb_strtolower($c->doc->titles[0] ?? '')) {
            $problems[] = 'It repeats the page title word for word.';
        }
        if ($length < 50) {
            $problems[] = 'Short — it may not describe the page well enough.';
        } elseif ($length > 160) {
            $problems[] = 'Longer than about 160 characters, so it may be shortened in results.';
        }

        if ($problems !== []) {
            return $this->warning('meta.description', 'The meta description needs attention', [...$evidence, ...$problems], $why, $fix,
                'Length guidance is a display guideline, not a ranking rule.');
        }

        return $this->passed('meta.description', 'The page has a meta description', $evidence, $why);
    }

    /**
     * Canonical URLs from <link rel="canonical"> and the Link header, absolute and de-duplicated.
     *
     * @return list<string>
     */
    private function canonicalUrls(AuditContext $c): array
    {
        $hrefs = array_filter($c->doc->linkHrefs('canonical'), fn (string $href): bool => $href !== '');

        foreach ($c->page->headerValues('link') as $header) {
            foreach (explode(',', $header) as $part) {
                if (preg_match('/<([^>]+)>\s*;.*rel\s*=\s*"?canonical"?/i', $part, $match)) {
                    $hrefs[] = $match[1];
                }
            }
        }

        $urls = [];
        foreach ($hrefs as $href) {
            $absolute = $c->absolute($href);
            if ($absolute !== null && ! in_array($absolute, $urls, true)) {
                $urls[] = $absolute;
            }
        }

        $c->shared['canonical_relative'] = array_filter($hrefs, fn (string $href): bool => ! preg_match('#^https?://#i', trim($href))) !== [];

        return $urls;
    }

    /**
     * @param  list<string>  $urls
     */
    private function canonical(AuditContext $c, array $urls): Finding
    {
        $why = 'The canonical URL tells search engines which address is the main version of this content, so duplicates (tracking parameters, http/https, www/non-www) are combined instead of competing.';

        if ($urls === []) {
            return $this->warning('meta.canonical', 'No canonical URL', 'No <link rel="canonical"> in the HTML or Link header.', $why,
                'Add a self-referencing canonical with the full https:// address of this page. WordPress SEO plugins add it automatically when enabled.');
        }

        $canonical = $urls[0];
        $evidence = ["Canonical: {$canonical}"];

        if (count($urls) > 1) {
            return $this->warning('meta.canonical', 'More than one canonical URL', [...array_map(fn (string $u): string => "Canonical: {$u}", $urls), 'Conflicting canonicals may all be ignored.'], $why,
                'Keep a single canonical. Two are often added by the theme and an SEO plugin at the same time — switch one of them off.');
        }

        if ($c->shared['canonical_relative'] ?? false) {
            $evidence[] = 'Written as a relative address; a full https:// address is safer.';
        }

        $host = $c->host($canonical);

        if ($host !== $c->host()) {
            $evidence[] = "This page is on {$c->host()}; the canonical points to {$host}.";

            if (StagingHosts::looksLikeStaging($host) && ! $c->isStaging()) {
                return $this->critical('meta.canonical', 'The canonical URL points to a staging address', $evidence, $why,
                    'Update the site address settings (in WordPress, Settings → General and your SEO plugin) so canonicals use the production domain, then clear caches.');
            }

            if ($c->isStaging()) {
                return $this->passed('meta.canonical', 'The canonical URL points to another domain', $evidence, $why,
                    'Check this is the production domain. After launch, run the checker on the live site.');
            }

            return $this->warning('meta.canonical', 'The canonical URL points to another domain', $evidence, $why,
                'Unless this page is intentionally a copy of a page on that domain, point the canonical to this page\'s own address.');
        }

        if (! $c->sameUrl($canonical, $c->finalUrl())) {
            $evidence[] = "This page's address: {$c->finalUrl()}";
            $differs = parse_url($canonical, PHP_URL_SCHEME) !== parse_url($c->finalUrl(), PHP_URL_SCHEME)
                ? 'The canonical uses a different protocol (http vs https).'
                : 'The canonical is a different URL, so this page asks search engines to index that URL instead.';

            return $this->warning('meta.canonical', 'The canonical URL is a different address', [...$evidence, $differs], $why,
                'That is right only for duplicates or variants of another page. For a unique page, the canonical should be its own final https:// address (including the same trailing slash).');
        }

        return $this->passed('meta.canonical', 'The canonical URL is this page', $evidence, $why);
    }

    private function canonicalTarget(AuditContext $c, ?string $canonical): ?Finding
    {
        if ($canonical === null) {
            return null;
        }

        $why = 'A canonical must point to a page that loads with HTTP 200 directly. A canonical that redirects or errors sends search engines a contradictory signal.';

        if ($c->sameUrl($canonical, $c->finalUrl())) {
            return $this->passed('meta.canonical_target', 'The canonical URL loads', "{$canonical} → HTTP {$c->page->status}", $why);
        }

        $result = $c->fetch($canonical, ['max_redirects' => 0, 'max_bytes' => 1_000_000]);

        if ($result instanceof AuditException) {
            return $this->notChecked('meta.canonical_target', 'Whether the canonical URL loads', $this->failure($result), $why);
        }

        $evidence = "{$canonical} → HTTP {$result->status}".($result->header('location') ? ' to '.$result->header('location') : '');

        return match (true) {
            $result->successful() => $this->passed('meta.canonical_target', 'The canonical URL loads', $evidence, $why),
            $result->status >= 300 && $result->status < 400 => $this->warning('meta.canonical_target', 'The canonical URL redirects', $evidence, $why,
                'Point the canonical at the final address the redirect lands on.'),
            default => $this->finding('meta.canonical_target', $this->blockerUnlessStaging($c), 'The canonical URL doesn\'t load', $evidence, $why,
                'Point the canonical at a page that loads (HTTP 200), normally this page\'s own address.'),
        };
    }

    private function lang(AuditContext $c): Finding
    {
        $why = 'The lang attribute tells browsers, screen readers and translation tools which language the page is in.';
        $fix = 'Add the page language to the <html> element, for example <html lang="en">. WordPress sets it from Settings → General → Site Language.';

        if ($c->doc->lang === null || $c->doc->lang === '') {
            return $this->warning('meta.lang', 'No language declared', 'The <html> element has no lang attribute.', $why, $fix);
        }

        if (! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $c->doc->lang)) {
            return $this->warning('meta.lang', 'The language code looks invalid', "lang=\"{$c->doc->lang}\"", $why, $fix.' Use a standard code such as en, en-GB or hi-IN.');
        }

        return $this->passed('meta.lang', 'Language declared', "lang=\"{$c->doc->lang}\"", $why);
    }

    private function charset(AuditContext $c): Finding
    {
        $why = 'Without a declared character encoding, browsers may guess wrongly and show garbled characters.';
        $header = preg_match('/charset=([\w-]+)/i', (string) $c->page->header('content-type'), $match) ? strtolower($match[1]) : null;
        $meta = $c->doc->charset();

        if ($header === null && $meta === null) {
            return $this->warning('meta.charset', 'No character encoding declared', 'Neither the Content-Type header nor a <meta charset> declares one.', $why,
                'Add <meta charset="utf-8"> as the first element inside <head>.');
        }

        $evidence = array_values(array_filter([$header ? "Content-Type header: charset={$header}" : null, $meta ? "<meta> charset: {$meta}" : null]));

        return $this->passed('meta.charset', 'Character encoding declared', $evidence, $why);
    }

    private function viewport(AuditContext $c): Finding
    {
        $viewport = $c->doc->firstMeta('viewport');
        $why = 'Google indexes the mobile version of pages. Without a viewport tag, phones show a shrunken desktop layout.';
        $fix = 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> to the <head>.';

        if ($viewport === null) {
            return $this->warning('meta.viewport', 'No mobile viewport tag', 'No <meta name="viewport"> in the HTML.', $why, $fix);
        }

        $problems = [];
        if (! preg_match('/width\s*=\s*device-width/i', $viewport)) {
            $problems[] = 'It doesn\'t set width=device-width.';
        }
        if (preg_match('/user-scalable\s*=\s*(?:no|0)|maximum-scale\s*=\s*1(?:\.0)?(?![.\d])/i', $viewport)) {
            $problems[] = 'It stops visitors from zooming, which is an accessibility problem.';
        }

        if ($problems !== []) {
            return $this->warning('meta.viewport', 'The viewport tag needs attention', ["Viewport: {$this->quote($viewport)}", ...$problems], $why, $fix);
        }

        return $this->passed('meta.viewport', 'Mobile viewport declared', "Viewport: {$this->quote($viewport)}", $why);
    }

    private function favicon(AuditContext $c): Finding
    {
        $why = 'The favicon appears in browser tabs and bookmarks, and next to your site name in Google\'s mobile results.';
        $fix = 'Add a square icon (at least 48×48 px) with <link rel="icon" href="/favicon.png">. In WordPress, set the Site Icon under Appearance → Customize → Site Identity.';
        $declared = array_values(array_filter([...$c->doc->linkHrefs('icon'), ...$c->doc->linkHrefs('apple-touch-icon')]));

        foreach ($declared as $href) {
            if (str_starts_with(strtolower($href), 'data:image/')) {
                return $this->passed('meta.favicon', 'Favicon declared', 'Inline (data:) icon in the HTML.', $why);
            }
        }

        $url = $declared === [] ? $c->origin().'/favicon.ico' : $c->absolute($declared[0]);

        if ($url === null) {
            return $this->warning('meta.favicon', 'The favicon address is invalid', 'Declared icon: '.$this->quote($declared[0]), $why, $fix);
        }

        $result = $c->fetch($url, ['max_redirects' => 3, 'max_bytes' => 1_000_000, 'accept' => 'image/*,*/*;q=0.5']);

        if ($result instanceof AuditException) {
            return $this->notChecked('meta.favicon', 'Favicon', [$declared === [] ? 'No icon declared; tried /favicon.ico.' : "Declared: {$url}", $this->failure($result)], $why);
        }

        $isImage = $result->successful() && ! $result->isHtml();

        if ($declared === []) {
            return $isImage
                ? $this->passed('meta.favicon', 'Favicon found at /favicon.ico', "{$url} → HTTP {$result->status}", $why,
                    'Optional: also declare it with <link rel="icon"> so every browser finds it.')
                : $this->warning('meta.favicon', 'No favicon found', ['No <link rel="icon"> in the HTML.', "{$url} → HTTP {$result->status}"], $why, $fix);
        }

        return $isImage
            ? $this->passed('meta.favicon', 'Favicon declared and loads', "{$url} → HTTP {$result->status}", $why)
            : $this->warning('meta.favicon', 'The declared favicon doesn\'t load', "{$url} → HTTP {$result->status}".($result->isHtml() ? ' (an HTML page, not an image)' : ''), $why, $fix);
    }
}
