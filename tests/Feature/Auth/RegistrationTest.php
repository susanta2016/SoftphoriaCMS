<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AUTH-001 — public self-registration. Guest/Registered only: no
 * membership tier, no payment step.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_publicly_accessible_with_a_form(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('hp_website', false);
    }

    public function test_the_page_is_noindexed(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('noindex', false);
    }

    public function test_a_guest_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.thank-you'));

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame(UserStatus::PendingVerification->value, $user->status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registering_does_not_establish_a_session(): void
    {
        $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_submitting_without_required_fields_is_rejected(): void
    {
        $response = $this->from('/register')->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_an_unconfirmed_password_is_rejected(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'not-the-same',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        $response = $this->post('/register', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'hp_website' => 'https://spam.example',
        ]);

        $response->assertRedirect(route('register.thank-you'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_honeypot_submission_never_sends_any_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post('/register', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'hp_website' => 'https://spam.example',
        ]);

        Mail::assertNothingSent();
    }

    public function test_submitting_more_than_the_rate_limit_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', [
                'name' => 'Jane Visitor',
                'email' => "jane{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane-final@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    public function test_registering_sends_the_new_user_a_welcome_and_verification_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return $mail->hasTo('jane@example.com');
        });
    }

    public function test_registering_notifies_every_active_admin(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $admin = $this->admin();

        $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_a_mail_failure_does_not_prevent_registration(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->post('/register', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.thank-you'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
