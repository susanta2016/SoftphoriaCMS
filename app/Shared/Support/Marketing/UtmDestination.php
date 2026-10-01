<?php

namespace App\Shared\Support\Marketing;

/**
 * A UTM link's destination must be a page on this site: either a path
 * ("/register") or an http(s) URL whose host is APP_URL's host (a leading
 * "www." is ignored on both sides). Stored as the site-relative path (+
 * query/fragment) so the generated link always uses the configured
 * APP_URL. Anything else — another domain, protocol-relative "//", other
 * schemes (javascript:, data:, file:), embedded credentials, whitespace or
 * backslashes — is rejected, so a UTM link can never send a visitor off
 * the site.
 */
final class UtmDestination
{
    public const int MAX_LENGTH = 2048;

    /**
     * The site-relative form ("/path?query#fragment"), or null when the
     * value is not an acceptable destination.
     */
    public static function toRelative(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7F\\\\]/', $value)) {
            return null;
        }

        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '//')) {
                return null;
            }

            $parts = parse_url('http://placeholder.invalid'.$value);
        } else {
            $parts = parse_url($value);

            if ($parts === false
                || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass'])
                || ! self::isOwnHost($parts['host'] ?? '')) {
                return null;
            }
        }

        if ($parts === false) {
            return null;
        }

        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];

        return $path
            .(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '');
    }

    public static function isValid(?string $value): bool
    {
        return self::toRelative($value) !== null;
    }

    private static function isOwnHost(string $host): bool
    {
        $own = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        $normalize = fn (string $h): string => preg_replace('/^www\./', '', strtolower($h));

        return $host !== '' && $own !== '' && $normalize($host) === $normalize($own);
    }
}
