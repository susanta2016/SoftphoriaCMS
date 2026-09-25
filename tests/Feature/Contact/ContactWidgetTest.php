<?php

namespace Tests\Feature\Contact;

use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The site-wide Contact Us widget (resources/views/components/site/contact-widget.blade.php):
 * rendered on public pages, posting over fetch() to the same contact.submit
 * route as /contact and getting JSON back instead of a redirect.
 */
class ContactWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_renders_on_public_pages_with_a_honeypot(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-contact-widget', false);
        $response->assertSee('action="'.route('contact.submit').'"', false);
        $response->assertSee('id="cw-hp_website"', false);
    }

    public function test_the_widget_is_not_shown_on_the_contact_page(): void
    {
        $this->get('/contact')->assertOk()->assertDontSee('data-contact-widget', false);
    }

    public function test_a_json_submission_is_saved_and_answered_with_json(): void
    {
        $response = $this->postJson('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'phone' => '+91 90000 00000',
            'message' => 'Hello from the widget.',
        ]);

        $response->assertOk()->assertJson(['message' => 'Thank you — your message has been received.']);

        $this->assertDatabaseHas('contact_requests', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'phone' => '+91 90000 00000',
            'message' => 'Hello from the widget.',
        ]);
    }

    public function test_an_invalid_json_submission_gets_422_field_errors(): void
    {
        $response = $this->postJson('/contact', ['email' => 'not-an-email']);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_a_json_honeypot_submission_looks_successful_but_saves_nothing(): void
    {
        Mail::fake();

        $response = $this->postJson('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Spam',
            'hp_website' => 'http://spam.example',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('contact_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_the_admin_notification_carries_the_submission_with_visitor_input_escaped(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->postJson('/contact', [
            'name' => 'Jane <a href="http://evil.example">click</a>',
            'email' => 'jane@example.com',
            'phone' => '+91 90000 00000',
            'message' => "Line one\nLine two",
        ])->assertOk();

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($admin): bool {
            if (! $mail->hasTo($admin->email)) {
                return false;
            }

            $html = $mail->render();

            return str_contains($html, '+91 90000 00000')
                && str_contains($html, 'jane@example.com')
                && str_contains($html, 'Line one<br>')
                && str_contains($html, 'Jane &lt;a href=')
                && ! str_contains($html, '<a href="http://evil.example">');
        });
    }
}
