<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Models\Entitlement;

/**
 * What ResolveTrackAccessAction found, before AuthorizeTrackDownloadAction
 * turns it into an actual download (or a denial). `entitlement` is null for
 * a membership-derived grant — membership access is never a row, it's
 * checked live against Subscription::isActive() (see Subscription's
 * docblock).
 */
final readonly class AccessGrant
{
    public function __construct(
        public DownloadAccessType $type,
        public ?Entitlement $entitlement,
    ) {}
}
