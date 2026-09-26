<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\CreateResourceSubmission;
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
 * List + View, plus an env-gated admin "Add Resource" create page
 * (INSPIRATIONAL_RESOURCES_ADMIN_CREATE_ENABLED) — a pure review
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

    public function test_create_page_is_refused_when_the_env_flag_is_off(): void
    {
        config(['features.inspirational_resources_admin_create_enabled' => false]);

        $response = $this->actingAs($this->admin())->get('/admin/resource-submissions/create');

        $response->assertForbidden();

        Livewire::actingAs($this->admin())
            ->test(ListResourceSubmissions::class)
            ->assertActionHidden('create');
    }

    public function test_create_page_is_available_when_the_env_flag_is_on(): void
    {
        config(['features.inspirational_resources_admin_create_enabled' => true]);

        $response = $this->actingAs($this->admin())->get('/admin/resource-submissions/create');

        $response->assertOk();

        Livewire::actingAs($this->admin())
            ->test(ListResourceSubmissions::class)
            ->assertActionVisible('create');
    }

    public function test_admin_can_add_an_approved_resource_without_sending_emails(): void
    {
        config(['features.inspirational_resources_admin_create_enabled' => true]);
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateResourceSubmission::class)
            ->assertSchemaStateSet(['email' => $admin->email, 'status' => ResourceSubmissionStatus::Approved->value])
            ->fillForm([
                'name' => 'Cory Gold',
                'subject' => 'Submit',
                'category' => ResourceSubmission::OTHER_CATEGORY,
                'category_other' => ' Encouragement ',
                'theme' => 'Faith',
                'message' => 'A few words of light.',
                'reference_url' => 'https://example.com/story',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $submission = ResourceSubmission::query()->where('category', 'Encouragement')->firstOrFail();

        $this->assertSame(ResourceSubmissionStatus::Approved, $submission->status);
        $this->assertSame($admin->id, $submission->user_id);
        $this->assertSame('Cory Gold', $submission->name);
        $this->assertSame('submit-1', $submission->slug);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        $this->get(route('inspirational-resources.show', $submission))->assertOk()->assertSee('A few words of light.');
    }

    public function test_admin_create_validates_required_fields(): void
    {
        config(['features.inspirational_resources_admin_create_enabled' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateResourceSubmission::class)
            ->fillForm(['category' => '', 'theme' => null, 'message' => ''])
            ->call('create')
            ->assertHasFormErrors(['category' => 'required', 'theme' => 'required', 'message' => 'required']);

        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_admin_create_uses_the_public_forms_category_list(): void
    {
        config(['features.inspirational_resources_admin_create_enabled' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateResourceSubmission::class)
            ->assertFormFieldHidden('category_other')
            ->fillForm(['name' => 'Cory Gold', 'category' => 'Podcasts', 'theme' => 'Love', 'message' => 'Listen to this.'])
            ->assertFormFieldHidden('category_other')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Podcasts', ResourceSubmission::query()->sole()->category);

        // "Other" requires the admin's own category.
        Livewire::actingAs($this->admin())
            ->test(CreateResourceSubmission::class)
            ->fillForm(['name' => 'Cory Gold', 'category' => ResourceSubmission::OTHER_CATEGORY, 'theme' => 'Love', 'message' => 'More.'])
            ->assertFormFieldVisible('category_other')
            ->call('create')
            ->assertHasFormErrors(['category_other' => 'required']);

        // A category outside the list is refused.
        Livewire::actingAs($this->admin())
            ->test(CreateResourceSubmission::class)
            ->fillForm(['name' => 'Cory Gold', 'category' => 'Encouragement', 'theme' => 'Love', 'message' => 'More.'])
            ->call('create')
            ->assertHasFormErrors(['category']);

        $this->assertSame(1, ResourceSubmission::query()->count());
    }

    public function test_admin_can_delete_a_resource_from_the_list(): void
    {
        $submission = $this->createSubmission(['status' => ResourceSubmissionStatus::Approved, 'slug' => 'my-story']);
        $this->get(route('inspirational-resources.show', 'my-story'))->assertOk();
        $submission->seo()->create(['meta_title' => 'Custom title']);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListResourceSubmissions::class)
            ->callTableAction('delete', $submission)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($submission);
        $this->assertDatabaseMissing('seo_metadata', ['seoable_id' => $submission->id, 'seoable_type' => $submission->getMorphClass()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource_submission.deleted', 'user_id' => $admin->id]);
        $this->get(route('inspirational-resources.show', 'my-story'))->assertNotFound();
    }

    public function test_admin_can_bulk_delete_and_delete_from_the_view_page(): void
    {
        $first = $this->createSubmission();
        $second = $this->createSubmission();
        $kept = $this->createSubmission();

        Livewire::actingAs($this->admin())
            ->test(ListResourceSubmissions::class)
            ->callTableBulkAction('delete', [$first, $second]);

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);

        Livewire::actingAs($this->admin())
            ->test(ViewResourceSubmission::class, ['record' => $kept->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect('/admin/resource-submissions');

        $this->assertModelMissing($kept);
    }

    public function test_no_edit_route_exists(): void
    {
        $submission = $this->createSubmission();

        $response = $this->actingAs($this->admin())->get("/admin/resource-submissions/{$submission->id}/edit");

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
