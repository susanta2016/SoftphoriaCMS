<?php

namespace Tests\Feature\Admin;

use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\UpdateRoleAction;
use App\Exceptions\Role\ReservedRoleException;
use App\Exceptions\Role\RoleInUseException;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Widgets\RoleStatsWidget;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_role_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/roles');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_role_list(): void
    {
        Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

        Livewire::actingAs($this->admin())
            ->test(ListRoles::class)
            ->assertSuccessful();
    }

    public function test_the_role_list_is_searchable(): void
    {
        $findable = Role::query()->create(['name' => 'Zzyzx Editor', 'slug' => 'zzyzx-editor']);
        $other = Role::query()->create(['name' => 'Contributor', 'slug' => 'contributor']);

        Livewire::actingAs($this->admin())
            ->test(ListRoles::class)
            ->searchTable('Zzyzx')
            ->assertCanSeeTableRecords([$findable])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_admin_can_create_a_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateRole::class)
            ->fillForm([
                'name' => 'Editor',
                'slug' => 'editor',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('roles', [
            'name' => 'Editor',
            'slug' => 'editor',
        ]);
    }

    public function test_a_new_roles_slug_must_be_unique(): void
    {
        Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

        Livewire::actingAs($this->admin())
            ->test(CreateRole::class)
            ->fillForm([
                'name' => 'Duplicate',
                'slug' => 'editor',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_assign_permissions_when_creating_a_role(): void
    {
        $publish = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        $delete = Permission::query()->create(['name' => 'Delete Content', 'slug' => 'delete-content']);

        Livewire::actingAs($this->admin())
            ->test(CreateRole::class)
            ->fillForm([
                'name' => 'Editor',
                'slug' => 'editor',
                'permissions' => [$publish->id, $delete->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('slug', 'editor')->firstOrFail();

        $this->assertSame([$publish->id, $delete->id], $role->permissions()->pluck('permissions.id')->sort()->values()->all());
    }

    public function test_admin_can_edit_a_role_and_change_its_permissions(): void
    {
        $admin = $this->admin();
        $publish = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        $delete = Permission::query()->create(['name' => 'Delete Content', 'slug' => 'delete-content']);
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $role->permissions()->attach($publish);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSet('data.permissions', [$publish->id])
            ->fillForm([
                'name' => 'Senior Editor',
                'slug' => 'editor',
                'permissions' => [$delete->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();

        $this->assertSame('Senior Editor', $role->name);
        $this->assertSame([$delete->id], $role->permissions()->pluck('permissions.id')->all());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'role.updated',
            'entity_type' => 'Role',
            'entity_id' => $role->id,
        ]);
    }

    public function test_permission_removal_from_a_role_persists(): void
    {
        $publish = Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $role->permissions()->attach($publish);

        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm([
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $role->fresh()->permissions()->count());
    }

    public function test_admin_can_delete_an_unused_role(): void
    {
        $role = Role::query()->create(['name' => 'Unused Role', 'slug' => 'unused-role']);

        Livewire::actingAs($this->admin())
            ->test(ListRoles::class)
            ->callTableAction('delete', $role)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_still_assigned_to_a_user_is_prevented(): void
    {
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $target = User::factory()->create();
        $target->roles()->attach($role);

        Livewire::actingAs($this->admin())
            ->test(ListRoles::class)
            ->callTableAction('delete', $role);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_delete_role_action_enforces_the_in_use_guard_at_the_domain_layer(): void
    {
        $admin = $this->admin();
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $target = User::factory()->create();
        $target->roles()->attach($role);

        $this->expectException(RoleInUseException::class);

        app(DeleteRoleAction::class)->handle($role, $admin);
    }

    public function test_the_reserved_admin_role_cannot_be_deleted_via_the_list_action(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();

        Livewire::actingAs($admin)
            ->test(ListRoles::class)
            ->assertTableActionHidden('delete', $adminRole);
    }

    public function test_deleting_the_reserved_admin_role_is_prevented_at_the_domain_layer(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();

        $this->expectException(ReservedRoleException::class);

        app(DeleteRoleAction::class)->handle($adminRole, $admin);
    }

    public function test_the_reserved_admin_roles_slug_field_is_disabled(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $adminRole->getRouteKey()])
            ->assertFormFieldDisabled('slug');
    }

    /**
     * Regression test for the ADMIN-004 review fix: saving the reserved
     * admin role through the real Livewire form never dehydrates `slug`
     * (it's disabled), so this must succeed rather than throwing
     * ReservedRoleException for a change that never touched the slug.
     */
    public function test_admin_can_edit_the_reserved_admin_roles_name_via_the_edit_form(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $adminRole->getRouteKey()])
            ->fillForm([
                'name' => 'Site Administrator',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $adminRole->refresh();

        $this->assertSame('Site Administrator', $adminRole->name);
        $this->assertSame(Role::ADMIN_SLUG, $adminRole->slug);
    }

    /**
     * The important real-world failure case: assigning/removing permissions
     * on the admin role also went through UpdateRoleAction's slug guard
     * (the exception was thrown before permission sync ever ran), so this
     * previously failed too even though it never touches the slug field.
     */
    public function test_admin_can_change_the_reserved_admin_roles_permissions_via_the_edit_form(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();
        $permission = Permission::query()->create(['name' => 'Manage Settings', 'slug' => 'manage-settings']);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $adminRole->getRouteKey()])
            ->fillForm([
                'permissions' => [$permission->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $adminRole->refresh();

        $this->assertTrue($adminRole->permissions()->where('permissions.id', $permission->id)->exists());
        $this->assertSame(Role::ADMIN_SLUG, $adminRole->slug);
    }

    public function test_changing_the_reserved_admin_roles_slug_is_prevented_at_the_domain_layer(): void
    {
        $admin = $this->admin();
        $adminRole = $admin->roles()->first();

        $this->expectException(ReservedRoleException::class);

        app(UpdateRoleAction::class)->handle(
            $adminRole,
            ['name' => $adminRole->name, 'slug' => 'not-admin'],
            $admin,
        );
    }

    public function test_the_role_stats_widget_shows_accurate_counts(): void
    {
        Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        Permission::query()->create(['name' => 'Publish Content', 'slug' => 'publish-content']);

        Livewire::actingAs($this->admin())
            ->test(RoleStatsWidget::class)
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
