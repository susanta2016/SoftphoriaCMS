<?php

namespace Tests\Feature\PoetryProse;

use App\Filament\Pages\Settings;
use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages\ListPoetryProseSubmissions;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages\ViewPoetryProseSubmission;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Poetry/Prose (Light Posts) "Submit Your Writing" form — separate
 * from, and never writing to, Inspirational Resources' submissions — plus
 * its admin inbox (Poetry/Prose → Submissions) and editable page copy.
 */
class PoetryProseSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Morning Light',
            'category' => 'Original Poetry',
            'theme' => 'Gratitude',
            'message' => 'A poem about the morning.',
            ...$overrides,
        ];
    }

    public function test_the_poetry_prose_sidebar_links_to_the_new_form(): void
    {
        $response = $this->get(route('poetry-prose.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('poetry-prose.create').'"', false);
        $response->assertDontSee('href="'.route('inspirational-resources.create').'"', false);
    }

    public function test_the_form_shows_the_poetry_prose_categories_and_themes(): void
    {
        $response = $this->get(route('poetry-prose.create'));

        $response->assertOk();
        $response->assertSee('Submit Your Writing');
        $response->assertSeeInOrder(['Testimony', 'Original Essay', 'Original Poetry', 'Reflection']);
        $response->assertSeeInOrder(['Inspiration', 'Love', 'Faith', 'Forgiveness', 'Giving', 'Gratitude']);
        $response->assertSee('name="hp_website"', false);
        $response->assertDontSee('Podcasts');
    }

    public function test_a_guest_submission_is_saved_to_the_poetry_prose_inbox_only(): void
    {
        $this->post(route('poetry-prose.submit'), $this->validPayload())
            ->assertRedirect(route('poetry-prose.create'))
            ->assertSessionHas('status');

        $submission = PoetryProseSubmission::query()->firstOrFail();
        $this->assertSame('Original Poetry', $submission->category);
        $this->assertSame('Gratitude', $submission->theme);
        $this->assertSame(PoetryProseSubmissionStatus::Submitted, $submission->status);
        $this->assertNull($submission->user_id);
        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_a_logged_in_submission_uses_the_account_identity(): void
    {
        $user = User::factory()->create(['name' => 'Real Name', 'email' => 'real@example.com']);

        $this->actingAs($user)->post(route('poetry-prose.submit'), $this->validPayload([
            'name' => 'Spoofed',
            'email' => 'spoofed@example.com',
        ]));

        $submission = PoetryProseSubmission::query()->firstOrFail();
        $this->assertSame($user->id, $submission->user_id);
        $this->assertSame('real@example.com', $submission->email);
    }

    public function test_an_inspirational_resources_category_is_rejected(): void
    {
        $this->post(route('poetry-prose.submit'), $this->validPayload(['category' => 'Books']))
            ->assertSessionHasErrors('category');

        $this->post(route('poetry-prose.submit'), $this->validPayload(['theme' => null]))
            ->assertSessionHasErrors('theme');

        $this->assertSame(0, PoetryProseSubmission::query()->count());
    }

    public function test_the_honeypot_silently_discards_the_submission(): void
    {
        $this->post(route('poetry-prose.submit'), $this->validPayload(['hp_website' => 'http://spam.example']))
            ->assertRedirect(route('poetry-prose.create'))
            ->assertSessionHas('status');

        $this->assertSame(0, PoetryProseSubmission::query()->count());
    }

    public function test_submitting_emails_the_admins_and_the_submitter(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        $this->post(route('poetry-prose.submit'), $this->validPayload());

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($admin->email)
            && str_contains($mail->subjectLine, 'Poetry/Prose Writing Submission'));
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
            && str_contains($mail->subjectLine, 'We Received Your Writing'));
    }

    public function test_the_page_heading_and_intro_are_editable_in_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertSet('data.poetry_prose.submit_page_heading', 'Submit Your Writing')
            ->fillForm([
                'general' => ['site_name' => 'Softphoria', 'site_url' => 'https://softphoria.test', 'maintenance_mode' => false],
                'poetry_prose' => [
                    'submit_page_heading' => 'Share a Light Post',
                    'submit_page_intro' => 'Send us your words.',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get(route('poetry-prose.create'))
            ->assertOk()
            ->assertSee('Share a Light Post')
            ->assertSee('Send us your words.');
    }

    public function test_admin_can_list_view_and_review_submissions(): void
    {
        $admin = $this->admin();
        $submission = PoetryProseSubmission::query()->create($this->validPayload());

        $this->actingAs($admin)->get('/admin/poetry-prose-submissions')->assertOk()->assertSee('Morning Light');

        Livewire::actingAs($admin)
            ->test(ListPoetryProseSubmissions::class)
            ->assertCanSeeTableRecords([$submission]);

        Livewire::actingAs($admin)
            ->test(ViewPoetryProseSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertSee('A poem about the morning.')
            ->callAction('markInReview')
            ->callAction('approve');

        $this->assertSame(PoetryProseSubmissionStatus::Approved, $submission->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'PoetryProseSubmission', 'entity_id' => $submission->id]);
    }

    public function test_approving_sends_only_the_approved_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();
        $submission = PoetryProseSubmission::query()->create($this->validPayload());

        Livewire::actingAs($admin)
            ->test(ViewPoetryProseSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('markInReview');

        Mail::assertNothingSent();

        Livewire::actingAs($admin)
            ->test(ViewPoetryProseSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('approve');

        Mail::assertSent(TemplatedNotificationMail::class, 1);
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
            && str_contains($mail->subjectLine, 'Your Writing Has Been Approved'));
    }

    public function test_archiving_sends_no_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $submission = PoetryProseSubmission::query()->create($this->validPayload());

        Livewire::actingAs($this->admin())
            ->test(ViewPoetryProseSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('archive');

        Mail::assertNothingSent();
    }

    public function test_non_admin_cannot_access_the_inbox(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/admin/poetry-prose-submissions')->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
