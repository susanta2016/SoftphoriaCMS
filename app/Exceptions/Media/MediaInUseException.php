<?php

namespace App\Exceptions\Media;

use App\Models\Media;
use RuntimeException;

/**
 * Thrown by DeleteMediaAction when a Media row is still referenced
 * elsewhere. Almost every table that references `media` declares
 * restrictOnDelete() at the schema level, but Media soft-deletes by default
 * (never forceDelete()), which never touches that constraint — this is the
 * domain-layer equivalent guard for the soft-delete path, mirroring
 * PageInUseByNavigationException's reasoning for Pages.
 */
class MediaInUseException extends RuntimeException
{
    /**
     * @param  array<int, string>  $usedBy
     */
    public static function forMedia(Media $media, array $usedBy): self
    {
        $list = implode(', ', $usedBy);

        return new self("\"{$media->original_filename}\" is still used as {$list}. Remove those references before deleting it.");
    }
}
