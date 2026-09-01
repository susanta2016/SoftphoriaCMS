<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which grant authorized a given download: a specific Entitlement (Single/
 * Album purchase), a live, active Subscription (Pro Member — see
 * Entitlement's absence for membership access, documented on Subscription),
 * or Free — a Podcast Episode download, which needs no purchase/subscription
 * at all (see App\Modules\Podcast\Actions\Download\
 * AuthorizePodcastEpisodeDownloadAction). A Free row's entitlement_id is
 * always null.
 */
enum DownloadAccessType: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case Membership = 'membership';
    case Free = 'free';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Membership => 'Membership',
            self::Free => 'Free',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'info',
            self::Membership => 'success',
            self::Free => 'gray',
        };
    }
}
