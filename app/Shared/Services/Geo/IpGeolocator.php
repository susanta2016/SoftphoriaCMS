<?php

namespace App\Shared\Services\Geo;

use App\Models\IpLocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Locale;
use Throwable;

/**
 * Resolves an IP address to an approximate location (country, region,
 * city, timezone, network) for admin review of blog comments/reactions.
 *
 * - One ip_locations row per IP, refreshed at most every REFRESH_DAYS, so
 *   repeat visitors never trigger repeat lookups.
 * - Private/reserved addresses (LAN, localhost, Docker) are recorded as
 *   such and never sent anywhere.
 * - Provider: ipinfo.io (commercial use allowed on its free tier). Works
 *   without a key at low volume; set IPINFO_TOKEN for higher limits.
 * - Never throws: a failed lookup is logged and stored with its error so
 *   commenting/reacting is never affected.
 *
 * Called after the response is sent (LookupIpLocation job), so visitors
 * never wait on it.
 */
class IpGeolocator
{
    public const REFRESH_DAYS = 30;

    public function locate(?string $ip): ?IpLocation
    {
        if (blank($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $existing = IpLocation::query()->where('ip', $ip)->first();

        if ($existing && $existing->looked_up_at?->gt(now()->subDays(self::REFRESH_DAYS)) && ! $existing->error) {
            return $existing;
        }

        $attributes = $this->isPublic($ip) ? $this->lookup($ip) : ['is_private' => true, 'provider' => null, 'error' => null];

        return IpLocation::query()->updateOrCreate(['ip' => $ip], [...$attributes, 'looked_up_at' => now()]);
    }

    public function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookup(string $ip): array
    {
        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get('https://ipinfo.io/'.rawurlencode($ip).'/json', array_filter(['token' => config('services.ipinfo.token')]));

            if ($response->failed() || $response->json('bogon')) {
                return ['provider' => 'ipinfo', 'is_private' => (bool) $response->json('bogon'), 'error' => $response->failed() ? 'HTTP '.$response->status() : null];
            }

            [$lat, $lng] = array_pad(explode(',', (string) $response->json('loc')), 2, null);
            $code = strtoupper((string) $response->json('country')) ?: null;

            return [
                'provider' => 'ipinfo',
                'is_private' => false,
                'country_code' => $code,
                'country' => $code ? $this->countryName($code) : null,
                'region' => $response->json('region'),
                'city' => $response->json('city'),
                'postal' => $response->json('postal'),
                'latitude' => is_numeric($lat) ? (float) $lat : null,
                'longitude' => is_numeric($lng) ? (float) $lng : null,
                'timezone' => $response->json('timezone'),
                'network' => $response->json('org'),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('IP geolocation lookup failed', ['ip' => $ip, 'exception' => $exception->getMessage()]);

            return ['provider' => 'ipinfo', 'is_private' => false, 'error' => substr($exception->getMessage(), 0, 250)];
        }
    }

    private function countryName(string $code): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, 'en') : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }
}
