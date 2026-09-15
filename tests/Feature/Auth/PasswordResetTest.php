<?php

namespace Tests\Feature\Auth;

use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Forgot-password / reset-password (points 2/11 of the confirmed spec) —
 * Laravel's stock password broker end to end, routed through the
 * already-seeded "password_reset" EmailTemplate
 * (AppServiceProvider::routeResetPasswordThroughEmailTemplates()), never
 * revealing whether a submitted email has an account.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_is_accessible_and_marked_noindex(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_a_known_email_sends_the_password_reset_notification(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Notification::fake() (the test above) intercepts before toMail() ever
     * runs, so it can't catch a mailable that would fail to actually send —
     * exactly what happened here: AppServiceProvider::
     * routeResetPasswordThroughEmailTemplates() returned the rendered
     * TemplatedNotificationMail without addressing it, and
     * Illuminate\Notifications\Channels\MailChannel only auto-addresses a
     * MailMessage return value, never a plain Mailable — it calls
     * $mailable->send($mailer) directly, which relies entirely on the
     * mailable's own $to property (Mailable::buildRecipients()). Without an
     * explicit ->to() call there, $to stays empty and Symfony Mailer rejects
     * the send outright ("An email must have a 'To', 'Cc', or 'Bcc'
     * header."). Asserted directly against the returned mailable rather than
     * via Mail::fake(): MailFake::send() only records an object passed to it
     * that is `instanceof Mailable`, but Mailable::send($mailer) itself
     * calls $mailer->send($this->buildView(), ...) — a rendered view array,
     * not the mailable — so this exact dispatch path is invisible to
     * Mail::assertSent() no matter how it's addressed.
     */
    public function test_the_reset_mailable_is_addressed_to_the_user(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        $mailable = (new ResetPassword('a-raw-token'))->toMail($user);

        $this->assertTrue($mailable->hasTo($user->email));
    }

    public function test_an_unknown_email_gets_the_identical_generic_response_and_sends_no_notification(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $known = $this->post(route('password.email'), ['email' => 'irrelevant@example.com']);
        $known->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_the_reset_password_page_is_accessible_with_a_token_and_marked_noindex(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'some-token', 'email' => 'jane@example.com']));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_a_valid_token_resets_the_password_and_the_old_password_stops_working(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password123', $user->password));
        $this->assertFalse(Hash::check('old-password123', $user->password));
    }

    public function test_an_invalid_token_fails_cleanly_without_revealing_account_existence(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->post(route('password.update'), [
            'token' => 'totally-invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('password.reset', ['token' => 'totally-invalid-token', 'email' => $user->email]));
        $response->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_a_reset_token_is_single_use(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $second = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-password123',
            'password_confirmation' => 'another-password123',
        ]);

        $second->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_the_reset_email_template_substitutes_the_real_reset_url(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        // The seeded default body is a generic placeholder that doesn't
        // reference any variable by name (EmailTemplateSeeder::defaultHtmlBody())
        // — an admin fills in {{reset_url}} themselves. Setting it explicitly
        // here is what actually exercises TemplatedMailer's substitution.
        EmailTemplate::query()
            ->where('notification_key', 'password_reset')
            ->update(['html_body' => '<p>Reset your password: {{reset_url}}</p>']);

        // Invokes the closure AppServiceProvider::routeResetPasswordThroughEmailTemplates()
        // registered via ResetPassword::toMailUsing() directly, confirming the
        // token makes it into a URL pointing at the real password.reset route
        // now that it exists (that method's own previously-pending Route::has() check).
        $mailable = (new ResetPassword('a-raw-token-for-rendering'))->toMail($user);
        $rendered = $mailable->render();

        $this->assertStringContainsString(
            route('password.reset', ['token' => 'a-raw-token-for-rendering', 'email' => $user->email]),
            $rendered,
        );
    }
}
