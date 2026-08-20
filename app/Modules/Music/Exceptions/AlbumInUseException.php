<?php

namespace App\Modules\Music\Exceptions;

use App\Modules\Music\Models\Album;
use RuntimeException;

/**
 * Thrown by DeleteAlbumAction when the album still has tracks.
 * tracks.album_id is cascadeOnDelete() at the DB level, but that only fires
 * on a real DELETE — Albums are soft-deleted (never forceDelete()), which
 * never touches that constraint, so this is the domain-layer guard against
 * silently orphaning tracks under a deleted album. Mirrors
 * App\Modules\Podcast\Exceptions\PodcastInUseException.
 */
class AlbumInUseException extends RuntimeException
{
    public static function forAlbum(Album $album, int $trackCount): self
    {
        $trackWord = $trackCount === 1 ? 'track' : 'tracks';

        return new self("\"{$album->title}\" still has {$trackCount} {$trackWord}. Delete or reassign them before deleting the album.");
    }
}
