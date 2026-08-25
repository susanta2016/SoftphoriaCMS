<?php

namespace App\Modules\InspirationalResources\Exceptions;

use RuntimeException;

/**
 * Guards CreatePoetryProseFromSubmissionAction at the domain layer — the
 * Filament action's own ->visible() check is a convenience, never the
 * actual authorization: a submission must never be drafted twice
 * regardless of what the UI currently shows.
 */
class ResourceSubmissionAlreadyProcessedException extends RuntimeException
{
    public static function alreadyDrafted(): self
    {
        return new self('This submission has already been drafted into a Poetry/Prose entry.');
    }

    public static function notApproved(): self
    {
        return new self('Only an Approved submission can be drafted into Poetry/Prose.');
    }
}
