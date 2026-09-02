<?php

namespace Tests\Feature\Contact;

use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public Contact Us page — an info section (admin-configured email/
 * address, Settings' Contact tab) plus a submission form. A submission is
 * always a private administrative record; the page itself is a normal,
 * indexable public page. Spam protection: a honeypot field
 * (ContactController::store()'s own docblock) plus route throttling.
 */
class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_publicly_accessible_with_contact_info_and_a_form(): void
    {
        // The 2026_09_02_110002_seed_contact_settings migration seeds these
        // values for real (not just as a code-level fallback), so they're
        // present on a fresh RefreshDatabase run without any test setup.
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertSee('Contact Us');
        $response->assertSee('jacobdiawarii@gmail.com');
        $response->assertSee('1372 Pheasant Chase Circle', false);
        $response->assertSee('name="message"', false);
    }

    public function test_the_page_is_never_marked_noindex_by_default(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertDontSee('noindex', false);
    }

    public function test_a_guest_can_submit_the_form(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'message' => 'Hello, I have a question.',
        ]);

        $response->assertRedirect(route('contact.index'));
        $response->assertSessionHas('status');

        $submission = ContactSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Doe', $submission->name);
        $this->assertSame('555-0100', $submission->phone);
        $this->assertSame('Hello, I have a question.', $submission->message);
    }

    public function test_phone_is_optional(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'No phone provided.',
        ]);

        $response->assertRedirect(route('contact.index'));
        $response->assertSessionDoesntHaveErrors();

        $submission = ContactSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertNull($submission->phone);
    }

    public function test_submitting_without_required_fields_is_rejected(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
        ]);

        $response->assertSessionHasErrors(['email', 'message']);
        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'message' => 'Hello.',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy my product now!',
            'hp_website' => 'https://spam.example.com',
        ]);

        // The bot gets the same success response a real visitor would —
        // no signal it was caught.
        $response->assertRedirect(route('contact.index'));
        $response->assertSessionHas('status');
        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_submitting_notifies_every_admin_via_the_templated_mailer(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello, I have a question.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($admin->email));
    }

    public function test_submitting_sends_the_submitter_a_receipt_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello, I have a question.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com'));
    }

    public function test_a_honeypot_submission_never_sends_any_email(): void
    {
        Mail::fake();
        $this->admin();

        $this->post(route('contact.submit'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy my product now!',
            'hp_website' => 'filled',
        ]);

        Mail::assertNothingSent();
    }

    public function test_there_is_no_public_listing_of_submissions(): void
    {
        ContactSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'A private message that must never appear publicly.',
        ]);

        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertDontSee('A private message that must never appear publicly.');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
