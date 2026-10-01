<?php

namespace App\Shared\Support\Analytics;

use App\Shared\Services\Settings\SettingsRepository;

/**
 * Website Setup → Analytics & Tracking, stored in the `settings` table under
 * group "analytics". Read once per instance (the public layout asks for
 * several values on every page). See AnalyticsIntegrations for how each
 * value is used.
 */
class AnalyticsSettings
{
    public const GROUP = 'analytics';

    /**
     * key => [default, storage type]
     */
    public const FIELDS = [
        'enabled' => [true, 'boolean'],
        'exclude_admins' => [true, 'boolean'],
        'ga4_id' => [null, 'string'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Every field, falling back to its default when never saved.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $stored = $this->settings->all(self::GROUP);

            $this->values = [];

            foreach (self::FIELDS as $key => [$default]) {
                $this->values[$key] = array_key_exists($key, $stored) ? $stored[$key] : $default;
            }
        }

        return $this->values;
    }

    /**
     * Saves the known fields present in $values (unknown keys are ignored).
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (self::FIELDS as $key => [, $type]) {
            if (array_key_exists($key, $values)) {
                $this->settings->set(self::GROUP, $key, $values[$key], $type);
            }
        }

        $this->values = null;
    }
}
