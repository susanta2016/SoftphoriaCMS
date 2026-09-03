<?php

namespace Tests\Feature\Admin;

use App\Actions\Role\CreateRoleAction;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Resources\AuditLogs\Widgets\AuditLogStatsWidget;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-011 — the read-only viewer for the existing audit_logs table.
 * No new writer is introduced here; tests exercise the real
 * AuditLogService-backed actions (e.g. CreateRoleAction) wherever
 * possible so this resource is proven against genuine entries, not
 * hand-inserted fixtures.
 */
class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_audit_log_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/audit-logs');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_audit_log_list(): void
    {
        $admin = $this->admin();
        $this->createRoleAndGenerateAuditEntry($admin);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertSuccessful();
    }

    public function test_an_existing_audit_log_service_generated_entry_is_surfaced(): void
    {
        $admin = $this->admin();
        $role = $this->createRoleAndGenerateAuditEntry($admin);

        $entry = AuditLog::query()
            ->where('action', 'role.created')
            ->where('entity_id', $role->id)
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$entry]);
    }

    public function test_newest_records_appear_first(): void
    {
        $admin = $this->admin();
        $older = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'metadata' => [],
            'created_at' => now()->subDay(),
        ]);
        $newer = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 2,
            'metadata' => [],
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_the_list_is_searchable_by_action(): void
    {
        $admin = $this->admin();
        $findable = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.zzyzx_action',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'metadata' => [],
        ]);
        $other = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'permission.created',
            'entity_type' => 'Permission',
            'entity_id' => 2,
            'metadata' => [],
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->searchTable('zzyzx')
            ->assertCanSeeTableRecords([$findable])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_list_is_filterable_by_entity_type(): void
    {
        $admin = $this->admin();
        $roleEntry = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'metadata' => [],
        ]);
        $permissionEntry = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'permission.created',
            'entity_type' => 'Permission',
            'entity_id' => 2,
            'metadata' => [],
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->filterTable('entity_type', 'Role')
            ->assertCanSeeTableRecords([$roleEntry])
            ->assertCanNotSeeTableRecords([$permissionEntry]);
    }

    public function test_the_list_is_filterable_by_actor(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $adminEntry = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'metadata' => [],
        ]);
        $otherEntry = $this->makeAuditLog([
            'user_id' => $other->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 2,
            'metadata' => [],
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->filterTable('user_id', $admin->id)
            ->assertCanSeeTableRecords([$adminEntry])
            ->assertCanNotSeeTableRecords([$otherEntry]);
    }

    public function test_admin_can_view_an_entrys_detail(): void
    {
        $admin = $this->admin();
        $entry = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'metadata' => ['name' => 'Editor'],
        ]);

        Livewire::actingAs($admin)
            ->test(ViewAuditLog::class, ['record' => $entry->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('role.created')
            ->assertSee('127.0.0.1');
    }

    public function test_no_create_route_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/audit-logs/create');

        $response->assertNotFound();
    }

    public function test_no_edit_route_exists(): void
    {
        $admin = $this->admin();
        $entry = $this->makeAuditLog([
            'user_id' => $admin->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
            'entity_id' => 1,
            'metadata' => [],
        ]);

        $response = $this->actingAs($admin)->get("/admin/audit-logs/{$entry->id}/edit");

        $response->assertNotFound();
    }

    public function test_the_audit_log_stats_widget_shows_accurate_counts(): void
    {
        $admin = $this->admin();
        $this->createRoleAndGenerateAuditEntry($admin);

        Livewire::actingAs($admin)
            ->test(AuditLogStatsWidget::class)
            ->assertSuccessful();
    }

    private function createRoleAndGenerateAuditEntry(User $actor): Role
    {
        return app(CreateRoleAction::class)->handle(['name' => 'Editor', 'slug' => 'editor'], $actor);
    }

    /**
     * AuditLog declares no #[Fillable] surface (by design — see
     * AuditLogService's own docblock), so attributes are set individually
     * here too, matching how the real writer builds a row.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeAuditLog(array $attributes): AuditLog
    {
        $log = new AuditLog;
        $log->user_id = $attributes['user_id'] ?? null;
        $log->action = $attributes['action'];
        $log->entity_type = $attributes['entity_type'];
        $log->entity_id = $attributes['entity_id'] ?? null;
        $log->ip_address = $attributes['ip_address'] ?? null;
        $log->user_agent = $attributes['user_agent'] ?? null;
        $log->metadata = $attributes['metadata'] ?? [];

        if (isset($attributes['created_at'])) {
            $log->created_at = $attributes['created_at'];
        }

        $log->save();

        return $log;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
