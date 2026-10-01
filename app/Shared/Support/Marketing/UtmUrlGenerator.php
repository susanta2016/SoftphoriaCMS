<?php

namespace App\Shared\Support\Marketing;

use Illuminate\Support\Arr;

/**
 * Builds a UTM link: APP_URL + the destination's site-relative path, its
 * existing query string kept as-is (minus any utm_* keys, so a parameter
 * never appears twice), then the non-empty UTM values, RFC 3986 encoded via
 * Arr::query(), then any #fragment.
 */
final class UtmUrlGenerator
{
    /**
     * @param  array<string, mixed>  $parameters  utm_* => value
     */
    public static function generate(string $destination, array $parameters): string
    {
        $relative = UtmDestination::toRelative($destination) ?? '/';

        [$withoutFragment, $fragment] = array_pad(explode('#', $relative, 2), 2, null);
        [$path, $query] = array_pad(explode('?', (string) $withoutFragment, 2), 2, '');

        $kept = array_filter(
            explode('&', (string) $query),
            fn (string $pair): bool => $pair !== ''
                && ! in_array(strtolower(urldecode(explode('=', $pair, 2)[0])), UtmParameters::KEYS, true),
        );

        $query = implode('&', array_filter([...$kept, Arr::query(UtmParameters::clean($parameters))]));

        return rtrim((string) config('app.url'), '/')
            .$path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== null ? '#'.$fragment : '');
    }
}
