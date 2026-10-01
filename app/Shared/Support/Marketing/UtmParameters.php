<?php

namespace App\Shared\Support\Marketing;

/**
 * The five standard UTM parameters — the single list shared by link
 * generation (UtmUrlGenerator), visitor capture (UtmAttribution) and the
 * matching nullable `users` columns.
 */
final class UtmParameters
{
    public const array KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public const int MAX_LENGTH = 100;

    /**
     * Admin-entered values: letters, digits, spaces and . _ - only. Still
     * URL-encoded on generation — this just keeps reports tidy.
     */
    public const string ADMIN_VALUE_REGEX = '/^[\pL\pN _.\-]+$/u';

    /**
     * Only the known keys, in canonical order, as trimmed non-empty strings
     * capped at MAX_LENGTH. Anything else (arrays, empty values, unknown
     * keys) is dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function clean(array $input): array
    {
        $clean = [];

        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $value));

            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, self::MAX_LENGTH);
            }
        }

        return $clean;
    }

    /**
     * Human label for a utm_source value: the configured channel label
     * (config/utm.php), otherwise the raw value.
     */
    public static function sourceLabel(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        return config("utm.channels.{$source}.label") ?? $source;
    }
}
