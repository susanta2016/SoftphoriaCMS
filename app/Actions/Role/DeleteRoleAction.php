<?php

namespace App\Actions\Role;

use App\Exceptions\Role\ReservedRoleException;
use App\Exceptions\Role\RoleInUseException;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * The only path by which a Role may be deleted (ADMIN-004). Blocks deleting
 * the reserved Role::ADMIN_SLUG role outright (see ReservedRoleException),
 * and blocks deleting any role still assigned to a user (see
 * RoleInUseException) — role_permissions rows for the role are removed via
 * the existing cascadeOnDelete() FK once the role itself is deleted, so no
 * separate pivot cleanup is needed here.
 */
class DeleteRoleAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Role $role, User $actor): void
    {
        if ($role->slug === Role::ADMIN_SLUG) {
            throw ReservedRoleException::forDeletion($role);
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            throw RoleInUseException::forUsers($role, $userCount);
        }

        $name = $role->name;

        $role->delete();

        $this->auditLog->record($actor, 'role.deleted', $role, ['name' => $name]);
    }
}
