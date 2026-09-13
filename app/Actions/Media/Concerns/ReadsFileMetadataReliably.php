<?php

namespace App\Actions\Media\Concerns;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;

/**
 * Shared by StoreUploadedMediaAction and ReplaceMediaFileAction — both read
 * a just-written file's mime type/size immediately after Filament's
 * FileUpload has already stored it on the target disk.
 *
 * On this project's local dev setup (the whole app directory bind-mounted
 * from the Windows host into the container over a 9p filesystem — WSL2's
 * bridge to the Windows drive), that immediate re-read can lose a race: the
 * write genuinely lands on disk, but a `stat` a moment later — potentially
 * on a different file handle/process — doesn't see it yet, and Flysystem
 * throws UnableToRetrieveMetadata even though the file is really there (the
 * exact bug this retry fixes; confirmed by the file existing on a later,
 * successful read). A normal local/production filesystem never exhibits
 * this, so the retry is a no-op there — it succeeds on the first attempt
 * every time and only ever matters on this class of bind mount.
 */
trait ReadsFileMetadataReliably
{
    /**
     * @return array{mimeType: string, size: int}
     */
    protected function readFileMetadata(string $disk, string $path, int $attempts = 5): array
    {
        $delayMicroseconds = 100_000; // 100ms, linear backoff
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return [
                    'mimeType' => $this->normalizeMimeType(Storage::disk($disk)->mimeType($path), $path),
                    'size' => Storage::disk($disk)->size($path),
                ];
            } catch (UnableToRetrieveMetadata $exception) {
                $lastException = $exception;

                if ($attempt < $attempts) {
                    usleep($delayMicroseconds * $attempt);
                }
            }
        }

        throw $lastException;
    }

    /**
     * .m4a is an MP4-family container, and depending on the encoder's ftyp
     * atom, PHP's fileinfo/finfo sniffs it as `video/mp4` rather than any
     * `audio/*` type (confirmed 2026-09-13 against a real upload) — even
     * though config('media.categories.audio.accepted_mime_types') now
     * allows `video/mp4` through Filament's upload validation so these
     * files aren't rejected outright. Left unnormalized, the stored
     * mime_type would make MediaCategory::fromMimeType() classify the file
     * as Video everywhere downstream (admin preview player, the Media
     * Library grid's category filter), despite it being genuine audio. Only
     * the specific video/mp4-sniffed-as-.m4a case is remapped — a real
     * .mp4 video keeps its correct mime_type.
     */
    private function normalizeMimeType(string $mimeType, string $path): string
    {
        if ($mimeType === 'video/mp4' && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'm4a') {
            return 'audio/mp4';
        }

        return $mimeType;
    }
}
