<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cached geolocation for one IP address (see IpGeolocator). Joined to
 * blog comments/reactions by their ip_address for admin display only.
 */
#[Fillable([
    'ip', 'country_code', 'country', 'region', 'city', 'postal', 'latitude', 'longitude',
    'timezone', 'network', 'is_private', 'provider', 'looked_up_at', 'error',
])]
class IpLocation extends Model
{
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'looked_up_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Short "City, Region, Country" line for tables.
     */
    public function summary(): string
    {
        if ($this->is_private) {
            return 'Private / local network';
        }

        if ($this->error && ! $this->country_code) {
            return 'Lookup failed';
        }

        $place = collect([$this->city, $this->region, $this->country])->filter()->unique()->implode(', ');

        return trim(($this->flag() ? $this->flag().' ' : '').($place ?: 'Unknown'));
    }

    /**
     * The country's flag emoji, built from its ISO code.
     */
    public function flag(): ?string
    {
        if (! $this->country_code || ! preg_match('/^[A-Z]{2}$/', $this->country_code)) {
            return null;
        }

        return implode('', array_map(
            fn (string $letter): string => mb_chr(0x1F1E6 + ord($letter) - ord('A')),
            str_split($this->country_code),
        ));
    }

    public function mapUrl(): ?string
    {
        return $this->latitude !== null && $this->longitude !== null
            ? "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map=10/{$this->latitude}/{$this->longitude}"
            : null;
    }
}
