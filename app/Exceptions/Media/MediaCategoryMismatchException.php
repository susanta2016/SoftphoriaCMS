<?php

namespace App\Exceptions\Media;

use App\Enums\MediaCategory;
use RuntimeException;

/**
 * Thrown when a Replace File upload's derived category doesn't match the
 * existing Media record's category (ADMIN-005) — replacement must stay
 * within the same category (image -> image, never image -> audio, etc).
 */
class MediaCategoryMismatchException extends RuntimeException
{
    public static function forReplace(?MediaCategory $existing, ?MediaCategory $new): self
    {
        $existingLabel = $existing?->getLabel() ?? 'an unrecognized type';
        $newLabel = $new?->getLabel() ?? 'an unrecognized type';

        return new self("The replacement file is {$newLabel}, but this record is {$existingLabel}. Replacement must stay within the same category.");
    }
}
