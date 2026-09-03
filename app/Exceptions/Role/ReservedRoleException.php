<?php

namespace App\Exceptions\Role;

use App\Models\Role;
use RuntimeException;

/**
 * Thrown by DeleteRoleAction/UpdateRoleAction to protect the reserved
 * Role::ADMIN_SLUG role — App\Models\User::canAccessPanel() gates every
 * admin panel login on a role with this exact slug existing, so deleting it
 * or renaming its slug away from "admin" would lock every administrator out
 * of /admin with no in-app way to recover (ADMIN-004 safety requirement).
 */
class ReservedRoleException extends RuntimeException
{
    public static function forDeletion(Role $role): self
    {
        return new self("\"{$role->name}\" is the reserved administrator role and cannot be deleted. Admin panel access depends on this role existing.");
    }

    public static function forSlugChange(Role $role): self
    {
        return new self("\"{$role->name}\"'s slug cannot be changed. Admin panel access is granted by matching this exact slug.");
    }
}
