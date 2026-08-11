<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The six account statuses defined by the Core Specification (Ch.41.3).
 * Backed by the existing users.status string column (DB-002) — no schema
 * change; this only gives the existing values a typed, labeled surface for
 * the admin panel.
 */
enum UserStatus: string implements HasColor, HasLabel
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';
    case Locked = 'locked';
    case Banned = 'banned';
    case Deleted = 'deleted';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending Verification',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Locked => 'Locked',
            self::Banned => 'Banned',
            self::Deleted => 'Deleted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::PendingVerification => 'warning',
            self::Suspended, self::Locked => 'warning',
            self::Banned, self::Deleted => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
