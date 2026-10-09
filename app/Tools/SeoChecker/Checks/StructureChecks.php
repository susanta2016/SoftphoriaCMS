<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\FetchResult;
use App\Tools\SeoChecker\Finding;
use JsonException;

/**
 * On-page structure: H1 and heading order, image alt attributes, links
 * (with a small sample checked for errors), JSON-LD syntax and the amount
 * of text in the raw HTML.
 */
class StructureChecks extends CheckGroup
{
    public const CATEGORY = 'structure';

    public const LABEL = 'On-page structure';

    public const INTERNAL_SAMPLE = 5;

    public const EXTERNAL_SAMPLE = 3;

    public function run(AuditContext $context): array
    {
        return [
            $this->h1($context),
            $this->headingOrder($context),
            $this->imageAlt($context),
            $this->links($context),
            $this->linkSample($context),
            $this->structuredData($context),
            $this->content($context),
        ];
    }

    private function h1(AuditContext $c): Finding
    {
        $h1s = array_values(array_filter($c->doc->headings, fn (array $h): bool => $h['level'] === 1));
        $why = 'The H1 is the page\'s main visible heading. It helps visitors, screen-reader users and search engines understand what the page is about.';
        $evidence = array_map(fn (array $h): string => 'H1: '.($h['text'] === '' ? '(empty)' : $this->quote($h['text'])), $h1s);

        if ($h1s === []) {
            return $this->warning('structure.h1', 'No H1 heading', 'No <h1> in the HTML.', $why,
                'Give the page one clear H1 that describes its main topic. In page builders such as Elementor, set the main heading widget\'s HTML tag to H1.');
        }
        if (array_filter($h1s, fn (array $h): bool => $h['text'] === '') !== []) {
            return $this->warning('structure.h1', 'An H1 heading is empty', $evidence, $why, 'Put the page\'s main heading text in the H1, or remove the empty H1.');
        }
        if (count($h1s) > 1) {
            return $this->warning('structure.h1', count($h1s).' H1 headings', $evidence, $why,
                'Consider one H1 for the main topic and H2s for sections. Often a logo or a theme heading is also marked as H1.',
                'Several H1s are not a Google penalty; one clear H1 is simply easier to understand.');
        }

        return $this->passed('structure.h1', 'One H1 heading', $evidence, $why);
    }

    private function headingOrder(AuditContext $c): Finding
    {
        $headings = $c->doc->headings;
        $why = 'Headings in order (H1 → H2 → H3) give the page an outline that screen readers use for navigation and that helps search engines understand sections.';

        if ($headings === []) {
            return $this->warning('structure.heading_order', 'No headings', 'The page has no <h1>–<h6> headings.', $why, 'Structure the content with an H1 and H2/H3 section headings.');
        }

        $counts = [];
        foreach ($headings as $h) {
            $counts[$h['level']] = ($counts[$h['level']] ?? 0) + 1;
        }
        ksort($counts);
        $evidence = [implode(' · ', array_map(fn (int $level, int $n): string => "H{$level}: {$n}", array_keys($counts), $counts))];

        $skips = [];
        $previous = 0;
        foreach ($headings as $h) {
            if ($previous > 0 && $h['level'] > $previous + 1) {
                $skips[] = "H{$previous} → H{$h['level']} before \"{$h['text']}\"";
            }
            $previous = $h['level'];
        }

        if ($skips !== []) {
            return $this->warning('structure.heading_order', 'Heading levels are skipped', [...$evidence, ...array_slice($skips, 0, 5)], $why,
                'Don\'t pick heading levels for their size; use the next level down and style it with CSS (or the page builder\'s typography settings).');
        }

        return $this->passed('structure.heading_order', 'Heading levels are in order', $evidence, $why);
    }

    private function imageAlt(AuditContext $c): Finding
    {
        $images = $c->doc->images;
        $why = 'Alt text describes an image to screen-reader users and to search engines (including image search). Decorative images should have an empty alt="".';

        if ($images === []) {
            return $this->passed('structure.image_alt', 'No images in the HTML', 'No <img> elements found.', $why, 'Nothing to do.', 'Images added by JavaScript or CSS backgrounds are not seen.');
        }

        $missing = array_values(array_filter($images, fn (array $img): bool => $img['alt'] === null));
        $decorative = count(array_filter($images, fn (array $img): bool => $img['alt'] === ''));
        $evidence = [count($images).' images; '.count($missing).' without an alt attribute; '.$decorative.' with an empty alt (decorative).'];

        if ($missing !== []) {
            foreach (array_slice($missing, 0, 5) as $img) {
                $evidence[] = 'Missing alt: '.($img['src'] !== '' ? $img['src'] : '(no src)');
            }

            return $this->warning('structure.image_alt', count($missing).' image'.(count($missing) === 1 ? '' : 's').' without alt text', $evidence, $why,
                'Add a short description as alt text to each meaningful image (in WordPress, in the Media Library\'s "Alternative Text" field), and alt="" to purely decorative ones.');
        }

        return $this->passed('structure.image_alt', 'Every image has an alt attribute', $evidence, $why, 'Nothing to do.', 'Whether the alt text is descriptive isn\'t judged.');
    }

    private function links(AuditContext $c): Finding
    {
        $why = 'Crawlers discover pages by following <a href> links. Links without a real address (# or javascript:) can\'t be followed.';
        $internal = $external = $nofollow = 0;
        $dead = [];
        $urls = ['internal' => [], 'external' => []];

        foreach ($c->doc->anchors as $anchor) {
            $href = $anchor['href'];
            if ($href === '' || $href === '#' || preg_match('#^javascript:#i', $href)) {
                $dead[] = $href === '' ? '(empty href)' : $href;

                continue;
            }
            if (str_starts_with($href, '#') || preg_match('#^(?:mailto|tel|sms):#i', $href)) {
                continue;
            }
            $url = $c->absolute($href);
            if ($url === null) {
                continue;
            }
            $kind = $c->host($url) === $c->host() ? 'internal' : 'external';
            $kind === 'internal' ? $internal++ : $external++;
            $nofollow += in_array('nofollow', $anchor['rel'], true) ? 1 : 0;
            $urls[$kind][] = $url;
        }
        $c->shared['link_urls'] = $urls;

        $evidence = ["{$internal} internal links, {$external} external links, {$nofollow} marked nofollow."];

        if ($dead !== []) {
            $evidence[] = count($dead).' link'.(count($dead) === 1 ? '' : 's').' without a real address, for example: '.implode(', ', array_slice(array_unique($dead), 0, 3));

            return $this->warning('structure.links', 'Some links have no crawlable address', $evidence, $why,
                'Give every navigation link a real href. Use <button> for elements that only run JavaScript.');
        }
        if ($internal === 0) {
            return $this->warning('structure.links', 'No internal links in the HTML', $evidence, $why,
                'Link to your other important pages with normal <a href> links. If the menu is built by JavaScript, check it renders real links.',
                'Links added by JavaScript after the page loads are not seen.');
        }

        return $this->passed('structure.links', 'Links found', $evidence, $why);
    }

    private function linkSample(AuditContext $c): Finding
    {
        $why = 'Broken links frustrate visitors and waste crawl effort. Checking a few before launch often reveals links still pointing at staging or deleted pages.';
        $urls = $c->shared['link_urls'] ?? ['internal' => [], 'external' => []];
        $pick = fn (array $list, int $n): array => array_slice(array_values(array_filter(array_unique($list), fn (string $u): bool => ! $c->sameUrl($u, $c->finalUrl()))), 0, $n);
        $sample = [...$pick($urls['internal'], self::INTERNAL_SAMPLE), ...$pick($urls['external'], self::EXTERNAL_SAMPLE)];
        $limitation = 'Only a sample of up to '.self::INTERNAL_SAMPLE.' internal and '.self::EXTERNAL_SAMPLE.' external links was checked, without following redirects. Some sites refuse automated requests (401/403/429), so those are reported as unverified, not broken.';

        if ($sample === []) {
            return $this->notChecked('structure.link_sample', 'Broken link sample', 'No links to sample.', $why, 'Nothing to do.');
        }

        $broken = [];
        $evidence = [];
        $checked = 0;
        foreach ($sample as $url) {
            $result = $c->fetch($url, ['method' => 'HEAD', 'max_redirects' => 0, 'timeout' => 5]);
            if ($result instanceof FetchResult && in_array($result->status, [403, 405, 501], true)) {
                $result = $c->fetch($url, ['max_redirects' => 0, 'max_bytes' => 262_144, 'timeout' => 5]);
            }
            if ($result instanceof AuditException) {
                $evidence[] = "{$url}: ".$result->getMessage();

                continue;
            }
            $checked++;
            $status = $result->status;
            $label = match (true) {
                in_array($status, [401, 403, 429], true) => 'unverified (refuses automated requests)',
                $status >= 400 => 'broken',
                $status >= 300 => 'redirects',
                default => 'OK',
            };
            $evidence[] = "{$url} → HTTP {$status} ({$label})";
            if ($label === 'broken') {
                $broken[] = $url;
            }
        }

        if ($checked === 0) {
            return $this->notChecked('structure.link_sample', 'Broken link sample', $evidence, $why, 'Run the check again later.', $limitation);
        }

        return $broken !== []
            ? $this->warning('structure.link_sample', count($broken).' broken link'.(count($broken) === 1 ? '' : 's').' in the sample', $evidence, $why,
                'Fix or remove the broken links. A full-site link crawl is worth running before launch.', $limitation)
            : $this->passed('structure.link_sample', 'No broken links in the sample', $evidence, $why, 'Nothing to do.', $limitation);
    }

    private function structuredData(AuditContext $c): Finding
    {
        $why = 'Structured data (usually JSON-LD) describes the page to search engines — organisation, article, product, FAQ and so on — and is required for some rich results.';
        $limitation = 'Only the JSON syntax is checked, not schema.org rules or rich-result eligibility. Use Google\'s Rich Results Test for that.';
        $blocks = $c->doc->jsonLd;

        if ($blocks === []) {
            return $c->doc->hasMicrodata
                ? $this->passed('structure.structured_data', 'Microdata or RDFa found', 'No JSON-LD; microdata/RDFa attributes are present (not validated).', $why, 'Nothing to do.', $limitation)
                : $this->warning('structure.structured_data', 'No structured data', 'No JSON-LD, microdata or RDFa found.', $why,
                    'Optional but recommended: add JSON-LD for your organisation or local business on the home page, and the matching type on articles and products. SEO plugins can generate it.', $limitation);
        }

        $types = [];
        $errors = [];
        foreach ($blocks as $i => $block) {
            try {
                $data = json_decode(trim($block), true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($data)) {
                    $errors[] = 'Block '.($i + 1).': not a JSON object or array.';

                    continue;
                }
                array_walk_recursive($data, function ($value, $key) use (&$types): void {
                    if ($key === '@type' && is_string($value)) {
                        $types[] = $value;
                    }
                });
            } catch (JsonException $e) {
                $errors[] = 'Block '.($i + 1).': invalid JSON ('.$e->getMessage().').';
            }
        }

        $evidence = [count($blocks).' JSON-LD block'.(count($blocks) === 1 ? '' : 's').'.'];
        if ($types !== []) {
            $evidence[] = 'Types: '.implode(', ', array_slice(array_unique($types), 0, 10));
        }

        if ($errors !== []) {
            return $this->warning('structure.structured_data', 'Structured data contains invalid JSON', [...$evidence, ...$errors], $why,
                'Fix the JSON syntax (a missing comma or quote, or unescaped characters are typical). Invalid blocks are ignored by search engines.', $limitation);
        }

        return $this->passed('structure.structured_data', 'Structured data found and parses', $evidence, $why, 'Nothing to do.', $limitation);
    }

    private function content(AuditContext $c): Finding
    {
        $words = $c->doc->wordCount;
        $why = 'Search engines need readable text to understand a page. Content that only appears after JavaScript runs may be indexed late or incompletely, and this checker can\'t see it.';
        $evidence = "About {$words} words of text in the HTML.";

        if ($words < 50) {
            return $this->warning('structure.content', 'Very little text in the HTML', [$evidence, ...($c->doc->hasAppRoot || $c->doc->scriptCount > 5 ? ['The page looks like a JavaScript app shell; the content is probably added by scripts.'] : [])], $why,
                'Make sure the main content is in the HTML the server sends (server-side rendering or pre-rendering for JavaScript frameworks), and that the page has enough useful text.',
                'This checker reads the raw HTML and doesn\'t run JavaScript.');
        }

        return $this->passed('structure.content', 'The HTML contains text content', $evidence, $why, 'Nothing to do.', 'Counted from the raw HTML; words are counted by spaces, so languages without spaces are undercounted.');
    }
}
