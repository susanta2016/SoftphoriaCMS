<?php

namespace Tests\Feature\InspirationalResources;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * The "Submit Your Writing" form page (client-confirmed 2026-09-02: moved
 * to its own page at inspirational-resources.create, reached from the
 * Inspirational Resources listing and from Poetry/Prose's sidebar — the
 * listing/detail pages themselves are covered by
 * InspirationalResourceListingTest). A submission is a private
 * administrative record until an admin approves it — approving it is what
 * makes it appear on the public listing/detail pages.
 */
class InspirationalResourceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_page_is_publicly_accessible(): void
    {
        $response = $this->get(route('inspirational-resources.create'));

        $response->assertOk();
        $response->assertSee('Submit Your Writing');
        $response->assertSee('Share your story');
    }

    public function test_the_form_offers_a_reference_url_field_instead_of_related_album_or_song(): void
    {
        $response = $this->get(route('inspirational-resources.create'));

        $response->assertOk();
        $response->assertSee('name="reference_url"', false);
        $response->assertDontSee('name="related_album_id"', false);
        $response->assertDontSee('name="related_track_id"', false);
    }

    public function test_the_form_page_is_never_marked_noindex_by_default(): void
    {
        $response = $this->get(route('inspirational-resources.create'));

        $response->assertDontSee('noindex', false);
    }

    public function test_a_guest_can_submit_the_form(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
        ]);

        $response->assertRedirect(route('inspirational-resources.create'));
        $response->assertSessionHas('status');

        $submission = ResourceSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertNull($submission->user_id);
        $this->assertSame('new', $submission->status->value);
        $this->assertNotNull($submission->slug);
    }

    public function test_the_form_offers_the_category_dropdown(): void
    {
        $response = $this->get(route('inspirational-resources.create'));

        $response->assertOk();
        $response->assertSee('<select', false);
        $response->assertSeeInOrder(['Books', 'Authors', 'Podcasts', 'Videos', 'Other']);
        $response->assertSee('name="category_other"', false);
    }

    public function test_choosing_other_stores_the_typed_in_category(): void
    {
        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Other',
            'category_other' => '  Music  ',
            'message' => 'Something meaningful happened.',
        ])->assertRedirect(route('inspirational-resources.create'));

        $this->assertSame('Music', ResourceSubmission::query()->firstOrFail()->category);
    }

    public function test_choosing_other_without_typing_a_category_fails(): void
    {
        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Other',
            'message' => 'Something meaningful happened.',
        ])->assertSessionHasErrors('category_other');

        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_a_category_outside_the_dropdown_is_rejected(): void
    {
        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Testimony',
            'message' => 'Something meaningful happened.',
        ])->assertSessionHasErrors('category');

        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_a_guest_can_submit_a_reference_url(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
            'reference_url' => 'https://example.com/the-story',
        ]);

        $response->assertRedirect(route('inspirational-resources.create'));

        $submission = ResourceSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('https://example.com/the-story', $submission->reference_url);
    }

    public function test_an_invalid_reference_url_is_rejected(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
            'reference_url' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('reference_url');
        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_an_authenticated_user_submission_records_their_user_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.submit'), [
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
        ]);

        $response->assertRedirect(route('inspirational-resources.create'));

        $submission = ResourceSubmission::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($user->id, $submission->user_id);
    }

    public function test_the_form_hides_name_and_email_fields_for_a_logged_in_user(): void
    {
        $user = User::factory()->create(['name' => 'Logged In User', 'email' => 'logged-in@example.com']);

        $response = $this->actingAs($user)->get(route('inspirational-resources.create'));

        $response->assertOk();
        $response->assertDontSee('name="name"', false);
        $response->assertDontSee('id="email"', false);
        $response->assertSee('Submitting as');
        $response->assertSee('Logged In User');
        $response->assertSee('logged-in@example.com');
    }

    public function test_the_form_shows_name_and_email_fields_for_a_guest(): void
    {
        $response = $this->get(route('inspirational-resources.create'));

        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('id="email"', false);
        $response->assertDontSee('Submitting as');
    }

    public function test_an_authenticated_users_submission_always_uses_their_own_account_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Real Name', 'email' => 'real@example.com']);

        $this->actingAs($user)->post(route('inspirational-resources.submit'), [
            'name' => 'Spoofed Name',
            'email' => 'spoofed@example.com',
            'category' => 'Books',
            'message' => 'Trying to submit under a different identity.',
        ]);

        $submission = ResourceSubmission::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Real Name', $submission->name);
        $this->assertSame('real@example.com', $submission->email);
    }

    public function test_submitting_without_required_fields_is_rejected(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
        ]);

        $response->assertSessionHasErrors(['email', 'category', 'message']);
        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_submitting_notifies_every_admin_via_the_templated_mailer(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($admin->email));
    }

    public function test_submitting_sends_both_the_submitter_acknowledgement_and_the_admin_notification(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Books',
            'message' => 'Something meaningful happened.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($admin->email)
            && str_contains($mail->subjectLine, 'Inspirational Resource Submission'));

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
            && str_contains($mail->subjectLine, 'We Received Your Submission'));
    }

    public function test_a_failed_submitter_acknowledgement_email_does_not_break_the_submission(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(TemplatedMailer::class, $mailer);

        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane.resilient@example.com',
            'category' => 'Books',
            'message' => 'Something meaningful happened even when email is down.',
        ]);

        $response->assertRedirect(route('inspirational-resources.create'));
        $this->assertSame(1, ResourceSubmission::query()->where('email', 'jane.resilient@example.com')->count());
    }

    public function test_an_unapproved_submission_has_no_public_detail_page(): void
    {
        $submission = ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Books',
            'message' => 'Private message.',
            'slug' => 'a-private-submission',
        ]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertNotFound();
    }

    public function test_submissions_never_appear_in_the_sitemap_until_approved(): void
    {
        ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A Very Distinctive Sitemap Subject',
            'category' => 'Books',
            'message' => 'Private message.',
            'slug' => 'a-very-distinctive-sitemap-subject',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('A Very Distinctive Sitemap Subject', false);
        $response->assertDontSee('inspirational-resources/a-very-distinctive-sitemap-subject', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
