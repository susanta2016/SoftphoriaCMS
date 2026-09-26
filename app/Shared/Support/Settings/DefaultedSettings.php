<?php

namespace App\Shared\Support\Settings;

use App\Shared\Services\Settings\SettingsRepository;

/**
 * A module's settings page stored as one group in the `settings` table,
 * where every key has a code default — so the module renders sensibly
 * before an admin has saved anything. Subclasses declare GROUP and FIELDS
 * (key => [default, storage type]).
 */
abstract class DefaultedSettings
{
    public const GROUP = '';

    /** @var array<string, array{0: mixed, 1: string}> */
    public const FIELDS = [];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * The request the cache was filled for. A controller holding this via
     * constructor injection is cached on its route, so the cache must not
     * outlive the request (matters under Octane and in feature tests).
     */
    private ?int $cachedFor = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(string $key): mixed
    {
        $request = app()->bound('request') ? spl_object_id(app('request')) : null;

        if ($this->cache === null || $this->cachedFor !== $request) {
            $this->cache = $this->settings->all(static::GROUP);
            $this->cachedFor = $request;
        }

        return $this->cache[$key] ?? static::FIELDS[$key][0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return collect(static::FIELDS)->keys()->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (static::FIELDS as $key => [, $type]) {
            if (array_key_exists($key, $values)) {
                $value = $values[$key];
                $this->settings->set(static::GROUP, $key, $type === 'boolean' ? (bool) $value : $value, $type);
            }
        }

        $this->cache = null;
    }
}
