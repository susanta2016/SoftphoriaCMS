<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Models\User;
use App\Shared\Services\AuditLogService;

class CreatePermissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Permission
    {
        $permission = new Permission;
        $permission->name = $data['name'];
        $permission->slug = $data['slug'];
        $permission->save();

        $this->auditLog->record($actor, 'permission.created', $permission, ['name' => $permission->name]);

        return $permission;
    }
}
