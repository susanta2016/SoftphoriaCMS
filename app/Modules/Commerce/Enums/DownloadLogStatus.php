<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DownloadLogStatus: string implements HasColor, HasLabel
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';

    public function getLabel(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::Denied => 'Denied',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Denied => 'danger',
        };
    }
}
