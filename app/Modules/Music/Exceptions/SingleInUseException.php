<?php

namespace App\Modules\Music\Exceptions;

use App\Modules\Music\Models\Single;
use RuntimeException;

/**
 * Thrown by DeleteSingleAction when the single still has its track. Same
 * reasoning as AlbumInUseException — soft-deleting the single never fires
 * the DB-level cascadeOnDelete() on tracks.single_id.
 */
class SingleInUseException extends RuntimeException
{
    public static function forSingle(Single $single): self
    {
        return new self("\"{$single->title}\" still has its song attached. Delete or reassign it before deleting the single.");
    }
}
