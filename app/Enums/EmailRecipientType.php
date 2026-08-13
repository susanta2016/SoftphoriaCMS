<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A single notification_key can have a `user` variant (the affected
 * user/member/customer), an `admin` variant (an internal notification), or
 * both — see EmailTemplate and docs/ARCHITECTURE.md §16.5.
 */
enum EmailRecipientType: string implements HasLabel
{
    case User = 'user';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Admin => 'Admin',
        };
    }
}
