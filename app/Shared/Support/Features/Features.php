<?php

namespace App\Shared\Support\Features;

use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Runtime on/off state of the frontend features registered in
 * config/features.php and switched on Admin → Website Setup → Features
 * Activation. Stored as boolean rows in the `settings` table (group
 * "features") — no table of its own.
 *
 * A feature is enabled only when its own switch is on AND every feature it
 * `requires` is enabled (so turning Blog Posts off also turns off
 * Categories, Tags, Comments and Reactions without touching their
 * switches). Registered as a singleton; stored values are read once per
 * request.
 */
class Features
{
    public const SETTINGS_GROUP = 'features';

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(string $key): bool
    {
        return $this->resolve($key, []);
    }

    public function disabled(string $key): bool
    {
        return ! $this->enabled($key);
    }

    /**
     * The admin's own switch for this feature, ignoring its requirements.
     */
    public function switchedOn(string $key): bool
    {
        $definition = $this->definition($key);

        if (($definition['toggleable'] ?? true) === false) {
            return true;
        }

        $stored = $this->stored();

        return array_key_exists($key, $stored) ? (bool) $stored[$key] : (bool) ($definition['default'] ?? true);
    }

    public function set(string $key, bool $on): void
    {
        $this->definition($key);
        $this->settings->set(self::SETTINGS_GROUP, $key, $on, 'boolean');
        $this->stored = null;
    }

    /**
     * True when a menu link points at a switched-off feature's pages (its
     * `links` patterns in config/features.php) — header/footer menus skip
     * those so nobody is sent to a 404. Absolute URLs on this site count
     * too; external URLs never match.
     */
    public function hidesLink(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return false;
        }

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        $candidate = $fragment !== null && $path === '/' ? '/#'.$fragment : rtrim($path, '/');

        foreach ($this->groups() as $group) {
            foreach ($group['features'] as $key => $feature) {
                foreach ($feature['links'] ?? [] as $pattern) {
                    if (Str::is($pattern, $candidate ?: '/') && $this->disabled($key)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, array{label: string, features: array<string, array<string, mixed>>}>
     */
    public function groups(): array
    {
        return config('features.groups', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $key): array
    {
        foreach ($this->groups() as $group) {
            if (isset($group['features'][$key])) {
                return $group['features'][$key];
            }
        }

        throw new InvalidArgumentException("Unknown feature [{$key}] — register it in config/features.php.");
    }

    /**
     * @param  array<int, string>  $visiting  guards against a requires-cycle in config
     */
    private function resolve(string $key, array $visiting): bool
    {
        if (in_array($key, $visiting, true) || ! $this->switchedOn($key)) {
            return false;
        }

        foreach ($this->definition($key)['requires'] ?? [] as $required) {
            if (! $this->resolve($required, [...$visiting, $key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return $this->stored ??= $this->settings->all(self::SETTINGS_GROUP);
    }
}
