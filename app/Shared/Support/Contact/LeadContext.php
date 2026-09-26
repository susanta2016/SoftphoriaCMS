<?php

namespace App\Shared\Support\Contact;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where a contact submission came from, for lead identification in
 * Admin → Contact Requests. Every contact form carries hidden fields
 * (resources/views/components/site/lead-context.blade.php) that JavaScript
 * fills in at submit time; this class validates them, because they arrive
 * from the visitor's browser:
 *
 * - page_url must be an http(s) URL on this site — otherwise the Referer
 *   header is used (same-site only), otherwise nothing;
 * - referrer (how the visitor first arrived, e.g. a search engine) may be
 *   any http(s) URL;
 * - source must be one of SOURCES; cta_label is plain, trimmed text.
 */
class LeadContext
{
    public const SOURCES = [
        'contact_page' => 'Contact page',
        'page_section' => 'Page contact section',
        'widget' => 'Side contact widget',
        'popup' => 'Call-to-action popup',
    ];

    /**
     * @return array{page_url: ?string, page_title: ?string, source: ?string, cta_label: ?string, referrer: ?string}
     */
    public static function fromRequest(Request $request): array
    {
        $pageUrl = self::sameSiteUrl($request->input('page_url')) ?? self::sameSiteUrl($request->headers->get('referer'));
        $source = $request->input('lead_source');

        return [
            'page_url' => $pageUrl,
            'page_title' => self::text($request->input('page_title'), 255),
            'source' => is_string($source) && array_key_exists($source, self::SOURCES) ? $source : null,
            'cta_label' => self::text($request->input('lead_cta'), 120),
            'referrer' => self::httpUrl($request->input('referrer')),
        ];
    }

    public static function sourceLabel(?string $source): ?string
    {
        return $source ? (self::SOURCES[$source] ?? $source) : null;
    }

    private static function sameSiteUrl(mixed $url): ?string
    {
        $url = self::httpUrl($url);

        if ($url === null) {
            return null;
        }

        return strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) parse_url(config('app.url'), PHP_URL_HOST)) === 0
            ? $url
            : null;
    }

    private static function httpUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return $clean === '' ? null : Str::limit($clean, $max, '');
    }
}
