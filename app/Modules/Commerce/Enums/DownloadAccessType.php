<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which grant authorized a given download: a specific Entitlement (Single/
 * Album purchase) or a live, active Subscription (Pro Member — see
 * Entitlement's absence for membership access, documented on Subscription).
 */
enum DownloadAccessType: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case Membership = 'membership';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Membership => 'Membership',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'info',
            self::Membership => 'success',
        };
    }
}
