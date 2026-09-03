<?php

namespace App\Exceptions\Role;

use App\Models\Role;
use RuntimeException;

/**
 * Thrown by DeleteRoleAction when a role is still assigned to one or more
 * users. Mirrors MediaInUseException/PageInUseByNavigationException's
 * domain-layer guard reasoning (docs/ARCHITECTURE.md §14).
 */
class RoleInUseException extends RuntimeException
{
    public static function forUsers(Role $role, int $userCount): self
    {
        $noun = $userCount === 1 ? 'user' : 'users';

        return new self("\"{$role->name}\" is still assigned to {$userCount} {$noun}. Reassign them before deleting this role.");
    }
}
