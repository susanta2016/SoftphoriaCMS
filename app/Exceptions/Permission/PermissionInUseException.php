<?php

namespace App\Exceptions\Permission;

use App\Models\Permission;
use RuntimeException;

/**
 * Thrown by DeletePermissionAction when a permission is still assigned to
 * one or more roles. Mirrors MediaInUseException/PageInUseByNavigationException's
 * domain-layer guard reasoning (docs/ARCHITECTURE.md §14).
 */
class PermissionInUseException extends RuntimeException
{
    /**
     * @param  array<int, string>  $roleNames
     */
    public static function forRoles(Permission $permission, array $roleNames): self
    {
        $list = implode(', ', $roleNames);

        return new self("\"{$permission->name}\" is still assigned to: {$list}. Remove it from those roles before deleting it.");
    }
}
