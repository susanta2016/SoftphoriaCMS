<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Not a database column — Entitlement's real state lives in revoked_at/
 * expires_at/downloads_used/max_downloads, and storing a redundant "status"
 * alongside those risks drifting out of sync with them. This is the
 * computed display value derived by Entitlement::status(), used by the
 * Filament table/infolist and nowhere else.
 */
enum EntitlementStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Exhausted = 'exhausted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
            self::Exhausted => 'Downloads Used Up',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Revoked => 'danger',
            self::Expired, self::Exhausted => 'warning',
        };
    }
}
