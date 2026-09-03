<?php

namespace App\Actions\Permission;

use App\Exceptions\Permission\PermissionInUseException;
use App\Models\Permission;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * The only path by which a Permission may be deleted (ADMIN-004). Blocks
 * deleting a permission still assigned to any role (see
 * PermissionInUseException) — role_permissions rows are removed via the
 * existing cascadeOnDelete() FK once the permission itself is deleted, so no
 * separate pivot cleanup is needed here.
 */
class DeletePermissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Permission $permission, User $actor): void
    {
        $roleNames = $permission->roles()->pluck('name')->all();

        if ($roleNames !== []) {
            throw PermissionInUseException::forRoles($permission, $roleNames);
        }

        $name = $permission->name;

        $permission->delete();

        $this->auditLog->record($actor, 'permission.deleted', $permission, ['name' => $name]);
    }
}
