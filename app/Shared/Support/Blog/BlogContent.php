<?php

namespace App\Shared\Support\Blog;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Presentation helpers for a blog post's rich-text body: reading time and
 * a table of contents. prepare() gives every <h2>/<h3> a stable id so the
 * table of contents (and anyone sharing a link) can jump straight to a
 * section — "jump to" links are also what search engines show as
 * sitelinks under a result.
 */
class BlogContent
{
    private const WORDS_PER_MINUTE = 220;

    public static function readingMinutes(?string $html): int
    {
        $words = str_word_count(strip_tags((string) $html));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>}
     */
    public static function prepare(?string $html): array
    {
        $html = (string) $html;

        if (trim($html) === '' || ! preg_match('/<h[23][\s>]/i', $html)) {
            return ['html' => $html, 'toc' => []];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML prolog makes libxml read the fragment as UTF-8.
        $document->loadHTML('<?xml encoding="UTF-8"?><div id="blog-body-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];
        $used = [];

        /** @var DOMElement $heading */
        foreach ((new DOMXPath($document))->query('//h2|//h3') as $heading) {
            $text = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');

            if ($text === '') {
                continue;
            }

            $id = $heading->getAttribute('id') ?: (Str::slug($text) ?: 'section');
            $base = $id;
            $n = 2;

            while (isset($used[$id])) {
                $id = "{$base}-{$n}";
                $n++;
            }

            $used[$id] = true;
            $heading->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) substr($heading->nodeName, 1)];
        }

        $root = $document->getElementById('blog-body-root');
        $out = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $document->saveHTML($child);
        }

        return ['html' => $out, 'toc' => $toc];
    }
}
