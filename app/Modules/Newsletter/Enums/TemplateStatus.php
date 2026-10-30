<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Only Active templates can be picked for a campaign. Archiving one never
 * affects campaigns already scheduled with it (they send from a snapshot).
 */
enum TemplateStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'warning',
            self::Archived => 'gray',
        };
    }
}
