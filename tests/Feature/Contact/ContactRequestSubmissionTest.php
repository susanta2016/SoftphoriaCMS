<?php

namespace Tests\Feature\Contact;

use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * ADMIN-010 — the public Contact Us page and submission flow. Mirrors the
 * test taxonomy proven by JacobCMS's own Contact form tests, adapted to
 * the Core contact_requests model/decision (see docs/ARCHITECTURE.md).
 */
class ContactRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_publicly_accessible_with_a_form(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('Contact Us');
        $response->assertSee('hp_website', false);
    }

    public function test_the_page_remains_indexable(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertDontSee('noindex');
    }

    public function test_a_guest_can_submit_the_form(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('contact_requests', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);
    }

    public function test_phone_subject_and_category_are_optional(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        $response->assertSessionHasNoErrors();

        $contactRequest = ContactRequest::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertNull($contactRequest->phone);
        $this->assertNull($contactRequest->subject);
        $this->assertNull($contactRequest->category);
    }

    public function test_phone_subject_and_category_are_persisted_when_provided(): void
    {
        $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'phone' => '+1 555 0100',
            'subject' => 'A question',
            'category' => 'support',
            'message' => 'Hello there.',
        ]);

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'jane@example.com',
            'phone' => '+1 555 0100',
            'subject' => 'A question',
            'category' => 'support',
        ]);
    }

    public function test_submitting_without_required_fields_is_rejected(): void
    {
        $response = $this->from('/contact')->post('/contact', []);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $response = $this->from('/contact')->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'not-an-email',
            'message' => 'Hello there.',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Spam.',
            'hp_website' => 'https://spam.example',
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHas('status');
        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_a_honeypot_submission_never_sends_any_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Spam.',
            'hp_website' => 'https://spam.example',
        ]);

        Mail::assertNothingSent();
    }

    public function test_submitting_more_than_the_rate_limit_is_throttled(): void
    {
        $payload = ['name' => 'Jane Visitor', 'email' => 'jane@example.com', 'message' => 'Hello there.'];

        for ($i = 0; $i < 6; $i++) {
            $this->post('/contact', $payload);
        }

        $response = $this->post('/contact', $payload);

        $response->assertStatus(429);
    }

    public function test_submitting_notifies_every_active_admin_via_the_templated_mailer(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $admin = $this->admin();

        $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'subject' => 'A question',
            'message' => 'Hello there.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_an_inactive_admin_is_not_notified(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->admin();
        $suspendedAdmin = User::factory()->create(['status' => 'suspended', 'email' => 'suspended-admin@example.com']);
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $suspendedAdmin->roles()->attach($adminRole);

        $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        Mail::assertNotSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return $mail->hasTo('suspended-admin@example.com');
        });
    }

    public function test_submitting_sends_the_submitter_a_receipt_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return $mail->hasTo('jane@example.com');
        });
    }

    public function test_a_mail_failure_does_not_prevent_persistence_or_a_successful_response(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));

        $response = $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('contact_requests', ['email' => 'jane@example.com']);
    }

    public function test_there_is_no_public_listing_of_submissions(): void
    {
        $response = $this->get('/contact/requests');

        $response->assertNotFound();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
