<?php

namespace App\Modules\Commerce\Support;

use Illuminate\Support\Carbon;

/**
 * The concrete limits an Entitlement is issued with — see
 * App\Modules\Commerce\Services\DownloadPolicy\DownloadPolicyResolver for
 * where these numbers come from. Snapshotted onto the Entitlement at
 * issuance (max_downloads/expires_at), same historical-snapshot principle as
 * price — an admin changing the policy afterward never alters an
 * already-issued entitlement.
 */
final readonly class DownloadPolicy
{
    public function __construct(
        public ?int $maxDownloads,
        public ?int $expiryDays,
    ) {}

    public function expiresAt(): ?Carbon
    {
        return $this->expiryDays === null ? null : now()->addDays($this->expiryDays);
    }
}
