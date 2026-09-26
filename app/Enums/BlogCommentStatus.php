<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Member comments publish instantly. An admin can Revoke one (hidden from
 * the site, kept for the record) and restore it later.
 */
enum BlogCommentStatus: string implements HasColor, HasLabel
{
    case Published = 'published';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Published => 'Published',
            self::Revoked => 'Revoked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Revoked => 'danger',
        };
    }
}
