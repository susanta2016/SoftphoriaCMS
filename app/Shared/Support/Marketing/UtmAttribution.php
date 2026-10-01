<?php

namespace App\Shared\Support\Marketing;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * First-touch UTM attribution for a guest's browsing session, kept in the
 * existing Laravel session (no extra cookie, no IP, no fingerprint). The
 * first UTM-tagged visit wins: later UTM links in the same session never
 * overwrite it. Registration copies it onto the new User (forUser()) and
 * then forgets it.
 */
final class UtmAttribution
{
    public const string SESSION_KEY = 'utm_attribution';

    public function captureFrom(Request $request): void
    {
        $session = $request->session();

        if ($session->has(self::SESSION_KEY)) {
            return;
        }

        $parameters = UtmParameters::clean($request->query());

        if ($parameters === []) {
            return;
        }

        $session->put(self::SESSION_KEY, [
            ...$parameters,
            'utm_landing_url' => mb_substr(self::landingUrl($request), 0, UtmDestination::MAX_LENGTH),
            'utm_captured_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * The captured attribution as `users` column => value, or [] when the
     * session has none.
     *
     * @return array<string, mixed>
     */
    public function forUser(Session $session): array
    {
        $stored = $session->get(self::SESSION_KEY);

        if (! is_array($stored)) {
            return [];
        }

        $attribution = UtmParameters::clean($stored);

        if ($attribution === []) {
            return [];
        }

        $landing = $stored['utm_landing_url'] ?? null;
        $capturedAt = $stored['utm_captured_at'] ?? null;

        return [
            ...$attribution,
            'utm_landing_url' => is_string($landing) ? mb_substr($landing, 0, UtmDestination::MAX_LENGTH) : null,
            'utm_captured_at' => is_string($capturedAt) ? Carbon::parse($capturedAt) : null,
        ];
    }

    /**
     * The landing URL with its query string exactly as the visitor sent it
     * (fullUrl() would re-sort the parameters).
     */
    private static function landingUrl(Request $request): string
    {
        $query = (string) $request->server('QUERY_STRING');

        return $request->url().($query !== '' ? '?'.$query : '');
    }

    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
