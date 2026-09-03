<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Role extends Model
{
    /**
     * The reserved slug App\Models\User::canAccessPanel() checks for admin
     * panel access. ADMIN-004's RoleResource guards this role's slug/deletion
     * against changes that would lock every administrator out — see
     * App\Actions\Role\DeleteRoleAction and App\Actions\Role\UpdateRoleAction.
     */
    public const string ADMIN_SLUG = 'admin';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
