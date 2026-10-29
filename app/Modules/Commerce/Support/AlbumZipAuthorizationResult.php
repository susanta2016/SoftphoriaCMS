<?php

namespace App\Modules\Commerce\Support;

/**
 * Returned by AuthorizeAlbumZipDownloadAction — on success, `zipPath` is a
 * freshly built temporary archive the controller streams and then deletes
 * (deleteFileAfterSend); on failure, `denialReason` is one of not_entitled /
 * limit_reached / no_audio_asset, same vocabulary as
 * DownloadAuthorizationResult. The DownloadLog row is already written
 * either way.
 */
final readonly class AlbumZipAuthorizationResult
{
    private function __construct(
        public bool $authorized,
        public ?string $zipPath,
        public ?string $filename,
        public ?string $denialReason,
    ) {}

    public static function granted(string $zipPath, string $filename): self
    {
        return new self(true, $zipPath, $filename, null);
    }

    public static function denied(string $reason): self
    {
        return new self(false, null, null, $reason);
    }
}
