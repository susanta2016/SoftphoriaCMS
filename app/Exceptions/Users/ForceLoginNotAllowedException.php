<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Thrown by App\Actions\Users\ForceLoginAsUserAction when the selected
 * account may not be signed into by an administrator. Messages are safe to
 * show to the acting admin.
 */
class ForceLoginNotAllowedException extends RuntimeException
{
    public static function forSelf(): self
    {
        return new self('You are already signed in as this account.');
    }

    public static function forAdminAccount(): self
    {
        return new self('Force Login is not available for administrator accounts.');
    }

    public static function forBlockedAccount(): self
    {
        return new self('Force Login is not available for suspended, locked, banned or deleted accounts.');
    }
}
