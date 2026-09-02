<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ListResourceSubmissions;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ViewResourceSubmission;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * List-only + View (submissions are created exclusively by
 * CreateResourceSubmissionAction from the public form) — a pure review
 * queue (client-confirmed, final): Submitted → In Review → Approved →
 * Archived, with no editorial conversion or relation to any other module.
 */
class ResourceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_submissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/resource-submissions');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_list(): void
    {
        $submission = $this->createSubmission();

        Livewire::actingAs($this->admin())
            ->test(ListResourceSubmissions::class)
            ->assertCanSeeTableRecords([$submission]);
    }

    public function test_no_create_or_edit_route_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/resource-submissions/create');

        $response->assertNotFound();
    }

    public function test_admin_can_approve_a_submission(): void
    {
        $submission = $this->createSubmission();
        $admin = $this->admin();

        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);

        $submission->refresh();
        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->status);
    }

    public function test_view_page_shows_the_submissions_details(): void
    {
        $submission = $this->createSubmission(['message' => 'A very specific message body.']);

        Livewire::actingAs($this->admin())
            ->test(ViewResourceSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertSee('A very specific message body.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSubmission(array $overrides = []): ResourceSubmission
    {
        return ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Testimony',
            'message' => 'A story worth sharing.',
            'status' => ResourceSubmissionStatus::Submitted,
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
