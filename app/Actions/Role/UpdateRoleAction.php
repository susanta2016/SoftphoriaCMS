<?php

namespace App\Actions\Role;

use App\Exceptions\Role\ReservedRoleException;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Updates a role's name/slug and its assigned permissions in one save,
 * consistent with UserForm/UpdateUserAction's single-request role
 * assignment. Guards the reserved Role::ADMIN_SLUG role's slug at the
 * domain layer (not just by disabling the field in RoleForm), per
 * docs/ARCHITECTURE.md §13's "enforce the same guard at the domain layer"
 * self-protection convention.
 *
 * The slug guard only runs `array_key_exists('slug', $data)` — RoleForm's
 * disabled slug field (for the reserved admin role) is therefore never
 * dehydrated/submitted, the same "missing key means the field wasn't part
 * of this submission" reasoning UpdateUserAction already applies to its
 * disabled `role_id` field. Without this check, editing the admin role's
 * name or permissions alone always threw ReservedRoleException, because a
 * missing `$data['slug']` compared as `null !== Role::ADMIN_SLUG`.
 */
class UpdateRoleAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Role $role, array $data, User $actor): Role
    {
        return DB::transaction(function () use ($role, $data, $actor): Role {
            if (array_key_exists('slug', $data)) {
                if ($role->slug === Role::ADMIN_SLUG && $data['slug'] !== Role::ADMIN_SLUG) {
                    throw ReservedRoleException::forSlugChange($role);
                }

                $role->slug = $data['slug'];
            }

            $role->name = $data['name'];
            $changedAttributes = array_keys($role->getDirty());
            $role->save();

            $newPermissionIds = $data['permissions'] ?? [];
            $currentPermissionIds = $role->permissions()->pluck('permissions.id')->all();
            sort($newPermissionIds);
            sort($currentPermissionIds);

            if ($newPermissionIds !== $currentPermissionIds) {
                $role->permissions()->sync($newPermissionIds);
                $changedAttributes[] = 'permissions';
            }

            if ($changedAttributes !== []) {
                $this->auditLog->record($actor, 'role.updated', $role, [
                    'changed' => $changedAttributes,
                ]);
            }

            return $role;
        });
    }
}
