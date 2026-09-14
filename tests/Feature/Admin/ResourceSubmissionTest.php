<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ListResourceSubmissions;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ViewResourceSubmission;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
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

    public function test_approving_a_submission_sends_the_published_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $submission = $this->createSubmission(['slug' => 'my-story']);
        $admin = $this->admin();

        app(ApproveResourceSubmissionAction::class)->handle($submission, $admin);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
            && str_contains($mail->subjectLine, 'Your Submission Has Been Published'));
    }

    public function test_a_merely_submitted_or_in_review_submission_never_gets_the_published_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->createSubmission(['slug' => 'still-pending']);

        Mail::assertNotSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => str_contains($mail->subjectLine, 'Your Submission Has Been Published'));
    }

    public function test_a_failed_published_email_does_not_break_the_approval(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $submission = $this->createSubmission(['slug' => 'my-story']);
        $admin = $this->admin();

        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(TemplatedMailer::class, $mailer);

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
