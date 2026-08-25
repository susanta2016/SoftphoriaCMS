<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\CreatePoetryProseFromSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Exceptions\ResourceSubmissionAlreadyProcessedException;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ListResourceSubmissions;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ViewResourceSubmission;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * List-only + View (submissions are created exclusively by
 * CreateResourceSubmissionAction from the public form) — the review-queue
 * status transitions and the one editorial conversion action
 * (client-confirmed, final workflow: Submitted → In Review → Approved →
 * Archived, with "Create Poetry/Prose Draft" the only outcome besides
 * Archive from Approved — there is no separate "publish as its own public
 * resource" step).
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

    public function test_approving_a_submission_never_creates_a_poetry_prose_draft(): void
    {
        $submission = $this->createSubmission();
        $admin = $this->admin();

        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);

        $submission->refresh();
        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->status);
        $this->assertNull($submission->poetry_prose_id);
    }

    public function test_creating_a_poetry_prose_draft_from_an_approved_submission(): void
    {
        $submission = $this->createSubmission(['subject' => 'My Story', 'message' => 'The full story text.']);
        $admin = $this->admin();
        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);

        $entry = app(CreatePoetryProseFromSubmissionAction::class)->handle($submission->refresh(), $admin);

        $this->assertSame('draft', $entry->status->value);
        $this->assertSame('My Story', $entry->title);
        $this->assertSame($entry->id, $submission->refresh()->poetry_prose_id);
        // Creating the draft never changes the submission's own Approved status.
        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->status);
    }

    public function test_creating_a_poetry_prose_draft_from_a_non_approved_submission_is_rejected(): void
    {
        $submission = $this->createSubmission();

        $this->expectException(ResourceSubmissionAlreadyProcessedException::class);

        app(CreatePoetryProseFromSubmissionAction::class)->handle($submission, $this->admin());
    }

    public function test_creating_a_poetry_prose_draft_from_the_same_submission_twice_is_rejected(): void
    {
        $submission = $this->createSubmission();
        $admin = $this->admin();
        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);
        app(CreatePoetryProseFromSubmissionAction::class)->handle($submission->refresh(), $admin);

        $this->expectException(ResourceSubmissionAlreadyProcessedException::class);

        app(CreatePoetryProseFromSubmissionAction::class)->handle($submission->refresh(), $admin);
    }

    /**
     * Client requirement: editing, publishing, or archiving the resulting
     * Poetry/Prose entry must never automatically alter the original
     * submission — the two stay independent records once linked.
     */
    public function test_the_resulting_poetry_prose_entry_and_the_submission_stay_independent(): void
    {
        $submission = $this->createSubmission();
        $admin = $this->admin();
        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);
        $entry = app(CreatePoetryProseFromSubmissionAction::class)->handle($submission->refresh(), $admin);

        $entry->status = 'published';
        $entry->title = 'A Completely Different Title';
        $entry->save();
        $entry->delete();

        $submission->refresh();
        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->status);
        $this->assertSame($entry->id, $submission->poetry_prose_id);
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
