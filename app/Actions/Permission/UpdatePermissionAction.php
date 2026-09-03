<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Models\User;
use App\Shared\Services\AuditLogService;

class UpdatePermissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Permission $permission, array $data, User $actor): Permission
    {
        $permission->name = $data['name'];
        $permission->slug = $data['slug'];
        $changedAttributes = array_keys($permission->getDirty());
        $permission->save();

        if ($changedAttributes !== []) {
            $this->auditLog->record($actor, 'permission.updated', $permission, [
                'changed' => $changedAttributes,
            ]);
        }

        return $permission;
    }
}
