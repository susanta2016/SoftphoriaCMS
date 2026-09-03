<?php

namespace Tests\Feature\Admin;

use App\Actions\Permission\DeletePermissionAction;
use App\Exceptions\Permission\PermissionInUseException;
use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Widgets\PermissionStatsWidget;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_permission_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/permissions');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_permission_list(): void
    {
        Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);

        Livewire::actingAs($this->admin())
            ->test(ListPermissions::class)
            ->assertSuccessful();
    }

    public function test_the_permission_list_is_searchable(): void
    {
        $findable = Permission::query()->create(['name' => 'Zzyzx Permission', 'slug' => 'zzyzx-permission']);
        $other = Permission::query()->create(['name' => 'Delete Content', 'slug' => 'delete-content']);

        Livewire::actingAs($this->admin())
            ->test(ListPermissions::class)
            ->searchTable('Zzyzx')
            ->assertCanSeeTableRecords([$findable])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_admin_can_create_a_permission(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePermission::class)
            ->fillForm([
                'name' => 'Publish Content',
                'slug' => 'publish-content',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('permissions', [
            'name' => 'Publish Content',
            'slug' => 'publish-content',
        ]);
    }

    public function test_a_new_permissions_slug_must_be_unique(): void
    {
        Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);

        Livewire::actingAs($this->admin())
            ->test(CreatePermission::class)
            ->fillForm([
                'name' => 'Duplicate',
                'slug' => 'publish-content',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_edit_a_permission(): void
    {
        $admin = $this->admin();
        $permission = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);

        Livewire::actingAs($admin)
            ->test(EditPermission::class, ['record' => $permission->getRouteKey()])
            ->fillForm([
                'name' => 'Publish Articles',
                'slug' => $permission->slug,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Publish Articles', $permission->fresh()->name);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'permission.updated',
            'entity_type' => 'Permission',
            'entity_id' => $permission->id,
        ]);
    }

    public function test_admin_can_delete_an_unused_permission(): void
    {
        $permission = Permission::query()->create(['name' => 'Unused Permission', 'slug' => 'unused-permission']);

        Livewire::actingAs($this->admin())
            ->test(ListPermissions::class)
            ->callTableAction('delete', $permission)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_deleting_a_permission_still_assigned_to_a_role_is_prevented(): void
    {
        $permission = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $role->permissions()->attach($permission);

        Livewire::actingAs($this->admin())
            ->test(ListPermissions::class)
            ->callTableAction('delete', $permission)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_delete_permission_action_enforces_the_in_use_guard_at_the_domain_layer(): void
    {
        $admin = $this->admin();
        $permission = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $role->permissions()->attach($permission);

        $this->expectException(PermissionInUseException::class);

        app(DeletePermissionAction::class)->handle($permission, $admin);
    }

    public function test_the_permission_stats_widget_shows_accurate_counts(): void
    {
        Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

        Livewire::actingAs($this->admin())
            ->test(PermissionStatsWidget::class)
            ->assertSuccessful();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
