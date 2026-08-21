<?php

namespace App\Modules\Commerce\Support;

use App\Models\Media;
use App\Modules\Commerce\Enums\DownloadAccessType;

/**
 * Returned by AuthorizeTrackDownloadAction — a future download controller's
 * entire job is to call the Action and, on success, stream `media`
 * (resolved from the private `local` disk, never a public URL); on failure,
 * return whatever HTTP status fits `denialReason`. The Action has already
 * written the DownloadLog row either way.
 */
final readonly class DownloadAuthorizationResult
{
    private function __construct(
        public bool $authorized,
        public ?Media $media,
        public ?DownloadAccessType $accessType,
        public ?string $denialReason,
    ) {}

    public static function granted(Media $media, DownloadAccessType $accessType): self
    {
        return new self(true, $media, $accessType, null);
    }

    public static function denied(string $reason): self
    {
        return new self(false, null, null, $reason);
    }
}
