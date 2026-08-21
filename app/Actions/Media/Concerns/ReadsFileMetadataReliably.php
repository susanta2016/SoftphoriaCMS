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
                    'mimeType' => Storage::disk($disk)->mimeType($path),
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
}
