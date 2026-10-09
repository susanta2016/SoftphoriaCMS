<?php

namespace App\Tools\SeoChecker;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * What the checks need from a page's raw HTML, read once with PHP's HTML5
 * parser. Nothing is executed (no scripts, no stylesheets, no subresources)
 * — this is the HTML as the server sent it, before any JavaScript ran.
 */
final class PageDocument
{
    /** @var list<string> */
    public array $titles = [];

    /** @var list<array{name: string, property: string, http_equiv: string, content: string, charset: string}> */
    public array $metas = [];

    /** @var list<array{rel: list<string>, href: string, sizes: string}> */
    public array $links = [];

    public ?string $lang = null;

    public ?string $baseHref = null;

    /** @var list<array{level: int, text: string}> */
    public array $headings = [];

    /** @var list<array{src: string, alt: ?string}> */
    public array $images = [];

    /** @var list<array{href: string, rel: list<string>}> */
    public array $anchors = [];

    /** @var list<string> raw JSON-LD blocks */
    public array $jsonLd = [];

    public bool $hasMicrodata = false;

    public int $wordCount = 0;

    public int $scriptCount = 0;

    public bool $hasAppRoot = false;

    /** @var list<string> every absolute http(s) URL referenced by an attribute */
    public array $absoluteUrls = [];

    public function __construct(string $html)
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $this->lang = $document->documentElement?->hasAttribute('lang') ? trim((string) $document->documentElement->getAttribute('lang')) : null;
        $this->baseHref = $this->attr($document->querySelector('base[href]'), 'href') ?: null;

        foreach ($document->querySelectorAll('title') as $title) {
            if ($title->closest('svg') === null) {
                $this->titles[] = self::clean($title->textContent);
            }
        }

        foreach ($document->querySelectorAll('meta') as $meta) {
            $this->metas[] = [
                'name' => strtolower(trim($this->attr($meta, 'name'))),
                'property' => strtolower(trim($this->attr($meta, 'property'))),
                'http_equiv' => strtolower(trim($this->attr($meta, 'http-equiv'))),
                'content' => self::clean($this->attr($meta, 'content'), 2000),
                'charset' => strtolower(trim($this->attr($meta, 'charset'))),
            ];
        }

        foreach ($document->querySelectorAll('link[rel]') as $link) {
            $this->links[] = [
                'rel' => $this->tokens($this->attr($link, 'rel')),
                'href' => trim($this->attr($link, 'href')),
                'sizes' => trim($this->attr($link, 'sizes')),
            ];
        }

        foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, h6') as $heading) {
            $this->headings[] = ['level' => (int) substr(strtolower($heading->localName), 1), 'text' => self::clean($heading->textContent, 200)];
        }

        foreach ($document->querySelectorAll('img') as $image) {
            $this->images[] = [
                'src' => trim($this->attr($image, 'src') ?: $this->attr($image, 'data-src')),
                'alt' => $image->hasAttribute('alt') ? trim((string) $image->getAttribute('alt')) : null,
            ];
        }

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $this->anchors[] = ['href' => trim($this->attr($anchor, 'href')), 'rel' => $this->tokens($this->attr($anchor, 'rel'))];
        }

        foreach ($document->querySelectorAll('script') as $script) {
            if (strtolower(trim($this->attr($script, 'type'))) === 'application/ld+json') {
                $this->jsonLd[] = (string) $script->textContent;
            } else {
                $this->scriptCount++;
            }
        }

        $this->hasMicrodata = $document->querySelector('[itemscope], [typeof], [vocab]') !== null;
        $this->hasAppRoot = $document->querySelector('#root, #app, #__next, #__nuxt, [data-reactroot], app-root') !== null;

        foreach ($document->querySelectorAll('[href], [src], [action], meta[content]') as $element) {
            foreach (['href', 'src', 'action', 'content'] as $name) {
                $value = trim($this->attr($element, $name));
                if (preg_match('#^https?://#i', $value)) {
                    $this->absoluteUrls[] = $value;
                }
            }
        }

        // Visible-ish text: the body without scripts, styles and templates.
        $body = $document->body;
        if ($body !== null) {
            foreach ($body->querySelectorAll('script, style, noscript, template, svg') as $hidden) {
                $hidden->remove();
            }
            $words = preg_split('/\s+/u', trim((string) $body->textContent), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->wordCount = count($words);
        }
    }

    /**
     * Contents of every <meta> whose name or property matches.
     *
     * @return list<string>
     */
    public function meta(string $key): array
    {
        $key = strtolower($key);
        $found = [];

        foreach ($this->metas as $meta) {
            if ($meta['name'] === $key || $meta['property'] === $key) {
                $found[] = $meta['content'];
            }
        }

        return $found;
    }

    public function firstMeta(string $key): ?string
    {
        foreach ($this->meta($key) as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * hrefs of every <link> carrying the given rel token.
     *
     * @return list<string>
     */
    public function linkHrefs(string $rel): array
    {
        $rel = strtolower($rel);

        return array_values(array_map(
            fn (array $link): string => $link['href'],
            array_filter($this->links, fn (array $link): bool => in_array($rel, $link['rel'], true)),
        ));
    }

    public function charset(): ?string
    {
        foreach ($this->metas as $meta) {
            if ($meta['charset'] !== '') {
                return $meta['charset'];
            }
            if ($meta['http_equiv'] === 'content-type' && preg_match('/charset=([\w-]+)/i', $meta['content'], $match)) {
                return strtolower($match[1]);
            }
        }

        return null;
    }

    /** Single line, no control characters, bounded length — safe as report evidence. */
    public static function clean(?string $text, int $limit = 300): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{2028}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', ' ', (string) $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
    }

    private function attr(?Element $element, string $name): string
    {
        return $element?->getAttribute($name) ?? '';
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        return array_values(array_filter(preg_split('/\s+/', strtolower(trim($value))) ?: []));
    }
}
