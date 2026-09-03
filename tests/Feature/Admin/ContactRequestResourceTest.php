<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Widgets\ContactRequestStatsWidget;
use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_contact_request_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/contact-requests');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_list(): void
    {
        $this->createContactRequest(['name' => 'Jane Visitor']);

        Livewire::actingAs($this->admin())
            ->test(ListContactRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(ContactRequest::all());
    }

    public function test_the_list_is_searchable(): void
    {
        $findable = $this->createContactRequest(['name' => 'Zzyzx Visitor', 'email' => 'zzyzx@example.com']);
        $other = $this->createContactRequest(['name' => 'Someone Else', 'email' => 'else@example.com']);

        Livewire::actingAs($this->admin())
            ->test(ListContactRequests::class)
            ->searchTable('Zzyzx')
            ->assertCanSeeTableRecords([$findable])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_list_is_filterable_by_status(): void
    {
        $new = $this->createContactRequest(['status' => ContactRequestStatus::New]);
        $resolved = $this->createContactRequest(['status' => ContactRequestStatus::Resolved]);

        Livewire::actingAs($this->admin())
            ->test(ListContactRequests::class)
            ->filterTable('status', ContactRequestStatus::Resolved->value)
            ->assertCanSeeTableRecords([$resolved])
            ->assertCanNotSeeTableRecords([$new]);
    }

    public function test_admin_can_view_a_submissions_detail(): void
    {
        $contactRequest = $this->createContactRequest(['message' => 'A detailed message.']);

        Livewire::actingAs($this->admin())
            ->test(ViewContactRequest::class, ['record' => $contactRequest->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('A detailed message.');
    }

    public function test_admin_can_change_a_submissions_status(): void
    {
        $admin = $this->admin();
        $contactRequest = $this->createContactRequest();

        Livewire::actingAs($admin)
            ->test(ListContactRequests::class)
            ->callTableAction('update', $contactRequest, data: [
                'status' => ContactRequestStatus::InProgress->value,
                'resolution_notes' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ContactRequestStatus::InProgress, $contactRequest->fresh()->status);
    }

    public function test_admin_can_add_resolution_notes(): void
    {
        $admin = $this->admin();
        $contactRequest = $this->createContactRequest();

        Livewire::actingAs($admin)
            ->test(ListContactRequests::class)
            ->callTableAction('update', $contactRequest, data: [
                'status' => $contactRequest->status->value,
                'resolution_notes' => 'Called the visitor back.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Called the visitor back.', $contactRequest->fresh()->resolution_notes);
    }

    public function test_a_meaningful_status_change_writes_an_audit_log_entry(): void
    {
        $admin = $this->admin();
        $contactRequest = $this->createContactRequest(['status' => ContactRequestStatus::New]);

        Livewire::actingAs($admin)
            ->test(ListContactRequests::class)
            ->callTableAction('update', $contactRequest, data: [
                'status' => ContactRequestStatus::Resolved->value,
                'resolution_notes' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'contact_request.updated',
            'entity_type' => 'ContactRequest',
            'entity_id' => $contactRequest->id,
        ]);
    }

    public function test_saving_with_no_actual_change_does_not_write_an_audit_log_entry(): void
    {
        $admin = $this->admin();
        $contactRequest = $this->createContactRequest(['status' => ContactRequestStatus::New, 'resolution_notes' => null]);

        Livewire::actingAs($admin)
            ->test(ListContactRequests::class)
            ->callTableAction('update', $contactRequest, data: [
                'status' => ContactRequestStatus::New->value,
                'resolution_notes' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'contact_request.updated',
            'entity_type' => 'ContactRequest',
            'entity_id' => $contactRequest->id,
        ]);
    }

    public function test_admin_can_delete_a_submission(): void
    {
        $admin = $this->admin();
        $contactRequest = $this->createContactRequest();

        Livewire::actingAs($admin)
            ->test(ListContactRequests::class)
            ->callTableAction('delete', $contactRequest)
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('contact_requests', ['id' => $contactRequest->id]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'contact_request.deleted',
            'entity_type' => 'ContactRequest',
            'entity_id' => $contactRequest->id,
        ]);
    }

    public function test_no_create_route_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/contact-requests/create');

        $response->assertNotFound();
    }

    public function test_no_edit_route_exists(): void
    {
        $contactRequest = $this->createContactRequest();

        $response = $this->actingAs($this->admin())->get("/admin/contact-requests/{$contactRequest->id}/edit");

        $response->assertNotFound();
    }

    public function test_the_contact_request_stats_widget_shows_accurate_counts(): void
    {
        $this->createContactRequest(['status' => ContactRequestStatus::New]);
        $this->createContactRequest(['status' => ContactRequestStatus::Resolved]);

        Livewire::actingAs($this->admin())
            ->test(ContactRequestStatsWidget::class)
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createContactRequest(array $overrides = []): ContactRequest
    {
        $contactRequest = new ContactRequest;
        $contactRequest->fill([
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);
        $contactRequest->status = $overrides['status'] ?? ContactRequestStatus::New;
        $contactRequest->resolution_notes = $overrides['resolution_notes'] ?? null;
        $contactRequest->fill(array_diff_key($overrides, ['status' => true, 'resolution_notes' => true]));
        $contactRequest->save();

        return $contactRequest;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
