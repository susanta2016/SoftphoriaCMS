<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * ADMIN-010 — backed by the existing contact_requests.status string column
 * (DB-002/003, default 'new') — no schema change, same pattern as
 * PageStatus/UserStatus.
 */
enum ContactRequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Spam => 'Spam',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Spam => 'danger',
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
