<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateRoleAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Role
    {
        return DB::transaction(function () use ($data, $actor): Role {
            $role = new Role;
            $role->name = $data['name'];
            $role->slug = $data['slug'];
            $role->save();

            $role->permissions()->sync($data['permissions'] ?? []);

            $this->auditLog->record($actor, 'role.created', $role, ['name' => $role->name]);

            return $role;
        });
    }
}
