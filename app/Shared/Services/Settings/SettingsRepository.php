<?php

namespace App\Shared\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * The single read/write entry point for the existing `settings` table
 * (DB-002/003, group+key+value+type) — see docs/ARCHITECTURE.md §16.2. No
 * module or Filament screen queries the `Setting` model directly; every
 * site-wide value (Website Setup's General/Email tabs, and any future
 * settings any later module needs) goes through this class instead of a
 * module-specific settings table.
 *
 * `type` drives how `value` round-trips: `boolean` casts to/from PHP bool,
 * `encrypted` round-trips through Laravel's Crypt facade so a value like
 * the SMTP password is never stored or read back in plaintext, everything
 * else is a plain string.
 */
class SettingsRepository
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->forGroup($group)->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $this->cast($setting);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(string $group): array
    {
        return Setting::query()->forGroup($group)->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $this->cast($setting)])
            ->all();
    }

    public function set(string $group, string $key, mixed $value, string $type = 'string'): void
    {
        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $this->prepareForStorage($value, $type), 'type' => $type],
        );
    }

    private function cast(Setting $setting): mixed
    {
        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'integer' => $setting->value !== null ? (int) $setting->value : null,
            'encrypted' => filled($setting->value) ? Crypt::decryptString($setting->value) : null,
            default => $setting->value,
        };
    }

    private function prepareForStorage(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'encrypted' => filled($value) ? Crypt::encryptString((string) $value) : null,
            default => $value === null ? null : (string) $value,
        };
    }
}
