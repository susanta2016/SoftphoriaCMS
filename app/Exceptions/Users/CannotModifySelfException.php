<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Thrown when an administrative action would change the acting
 * administrator's own status or access, which could lock them out of
 * /admin. See ADMIN-003 self-protection requirement.
 */
class CannotModifySelfException extends RuntimeException
{
    public static function forStatusChange(): self
    {
        return new self('Administrators cannot change the status of their own account.');
    }

    public static function forRoleChange(): self
    {
        return new self('Administrators cannot change their own role.');
    }

    public static function forPasswordGeneration(): self
    {
        return new self('Administrators cannot generate a new password for their own account.');
    }

    public static function forSessionRevocation(): self
    {
        return new self('Administrators cannot revoke their own sessions.');
    }
}
