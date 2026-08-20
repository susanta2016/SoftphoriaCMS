<?php

namespace App\Modules\Music\Exceptions;

use RuntimeException;

/**
 * Thrown by Track's model-level saving guard (see Track::booted()) when a
 * save would leave the record with both album_id and single_id set, or
 * neither. This is the backstop that fires regardless of how the record is
 * being saved — Filament form, Create/UpdateTrackAction, Tinker, a seeder,
 * a queued job, or a future importer/API — since it hooks Eloquent's
 * saving event rather than living only in the Action/UI layer. See also the
 * MariaDB-only CHECK constraint in
 * database/migrations/2026_08_22_090002_add_exactly_one_release_check_to_tracks_table.php
 * for the additional layer that catches raw SQL bypassing Eloquent
 * entirely.
 */
class InvalidTrackReleaseException extends RuntimeException
{
    public static function mustBelongToExactlyOne(): self
    {
        return new self('A track must belong to exactly one of an Album or a Single — never both, never neither.');
    }
}
