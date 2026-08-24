<?php

namespace Tests\Feature\Admin;

use App\Enums\EmailRecipientType;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup's Email Templates — Priority 2, docs/ARCHITECTURE.md
 * §16.5/§16.6. A fixed, seeded registry; no create/delete through the admin
 * UI, User/Admin tabs edit the two independent rows behind one
 * notification_key.
 */
class EmailTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_email_templates(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/website-setup/email-templates');

        $response->assertForbidden();
    }

    public function test_seeder_creates_one_row_per_configured_recipient(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $expected = collect(config('email_templates'))
            ->sum(fn (array $definition): int => count($definition['recipients']));

        $this->assertSame($expected, EmailTemplate::query()->count());
        $this->assertSame(
            count(config('email_templates')),
            EmailTemplate::query()->distinct('notification_key')->count('notification_key'),
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $countAfterFirstRun = EmailTemplate::query()->count();

        $this->seed(EmailTemplateSeeder::class);

        $this->assertSame($countAfterFirstRun, EmailTemplate::query()->count());
    }

    public function test_admin_can_view_the_list_showing_one_row_per_notification_key(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        Livewire::actingAs($this->admin())
            ->test(ListEmailTemplates::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(EmailTemplate::query()->where('recipient_type', 'user')->get());
    }

    public function test_no_create_action_exists(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        Livewire::actingAs($this->admin())
            ->test(ListEmailTemplates::class)
            ->assertActionDoesNotExist('create');
    }

    public function test_no_delete_action_exists_on_the_list(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        Livewire::actingAs($this->admin())
            ->test(ListEmailTemplates::class)
            ->assertTableActionDoesNotExist('delete');
    }

    public function test_admin_can_edit_the_user_variant(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['subject' => 'Please verify your {{site_name}} account'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Please verify your {{site_name}} account', $template->fresh()->subject);
    }

    public function test_editing_a_dual_recipient_key_saves_both_variants_independently(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $userVariant = EmailTemplate::query()->where('notification_key', 'user_registered')->where('recipient_type', 'user')->firstOrFail();
        $originalAdminSubject = EmailTemplate::query()->where('notification_key', 'user_registered')->where('recipient_type', 'admin')->firstOrFail()->subject;

        Livewire::actingAs($this->admin())
            ->test(EditEmailTemplate::class, ['record' => $userVariant->getRouteKey()])
            ->fillForm([
                'subject' => 'Welcome to {{site_name}}, {{user_name}}!',
                'admin' => ['subject' => 'New signup: {{user_email}}'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Welcome to {{site_name}}, {{user_name}}!', $userVariant->fresh()->subject);

        $adminVariant = EmailTemplate::query()->where('notification_key', 'user_registered')->where('recipient_type', 'admin')->firstOrFail();
        $this->assertSame('New signup: {{user_email}}', $adminVariant->subject);
        $this->assertNotSame($originalAdminSubject, $adminVariant->subject);
    }

    public function test_a_user_only_keys_edit_screen_has_no_admin_tab_and_does_not_create_one(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'profile_updated')->where('recipient_type', 'user')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['subject' => 'Your profile changed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, EmailTemplate::query()->where('notification_key', 'profile_updated')->count());
    }

    public function test_templated_mailer_sends_with_substituted_variables_when_enabled(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        app(TemplatedMailer::class)->send('email_verification', EmailRecipientType::User, 'someone@example.com', [
            'user_name' => 'Jane',
            'verification_url' => 'https://softphoria.test/verify/abc',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return $mail->hasTo('someone@example.com')
                && str_contains($mail->subjectLine, 'Verify Email');
        });
    }

    public function test_templated_mailer_does_nothing_when_the_template_is_disabled(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        EmailTemplate::query()->where('notification_key', 'email_verification')->update(['is_enabled' => false]);

        app(TemplatedMailer::class)->send('email_verification', EmailRecipientType::User, 'someone@example.com');

        Mail::assertNothingSent();
    }

    public function test_templated_mailer_does_nothing_when_the_key_does_not_exist(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        app(TemplatedMailer::class)->send('nonexistent_key', EmailRecipientType::User, 'someone@example.com');

        Mail::assertNothingSent();
    }

    public function test_variable_substitution_never_executes_template_content_as_code(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => '<p>{{user_name}} <?php echo "unsafe"; ?> {{ 1 + 1 }}</p>']);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);

        $this->assertNotNull($mailable);
        $rendered = $mailable->render();
        $this->assertStringContainsString('Jane', $rendered);
        // The literal Blade-style expression must survive untouched — proof
        // it was never passed to Blade::render() or eval().
        $this->assertStringContainsString('{{ 1 + 1 }}', $rendered);
        $this->assertStringContainsString('<?php echo "unsafe"; ?>', $rendered);
    }

    public function test_render_as_mailable_returns_null_when_disabled(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        EmailTemplate::query()->where('notification_key', 'password_reset')->update(['is_enabled' => false]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('password_reset', EmailRecipientType::User, []);

        $this->assertNull($mailable);
    }

    /**
     * Deliberately does not go through Mail::fake() + the full
     * $user->sendPasswordResetNotification() dispatch: Laravel's
     * Illuminate\Notifications\Channels\MailChannel calls
     * $message->send($mailerFactory) directly on a Mailable returned from
     * toMail() — a different call path than Mail::to()->send($mailable) —
     * which unwraps to a raw view array before ever reaching MailFake's own
     * instanceof-Mailable check, so MailFake silently drops it regardless
     * of whether AppServiceProvider's wiring is correct. Calling toMail()
     * directly tests the actual piece of logic this file owns (the
     * toMailUsing callback) without depending on that unrelated framework
     * behavior.
     */
    public function test_password_reset_notification_uses_the_templated_content_when_enabled(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        EmailTemplate::query()
            ->where('notification_key', 'password_reset')
            ->where('recipient_type', 'user')
            ->update(['subject' => 'Custom Reset Subject For {{site_name}}']);

        $user = User::factory()->create(['status' => 'active']);
        $notification = new ResetPassword('fake-token');

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(TemplatedNotificationMail::class, $mail);
        $this->assertStringContainsString('Custom Reset Subject For', $mail->subjectLine);
    }

    public function test_password_reset_falls_back_to_the_default_message_when_the_template_is_disabled(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        EmailTemplate::query()->where('notification_key', 'password_reset')->update(['is_enabled' => false]);

        $user = User::factory()->create(['status' => 'active']);
        $notification = new ResetPassword('fake-token');

        // The core safety guarantee: password reset delivery — functionally
        // required for account recovery — must never break just because an
        // admin disabled its Email Template. TemplatedMailer::renderAsMailable()
        // returns null for a disabled template, so the toMailUsing callback
        // falls back to Laravel's own built-in MailMessage.
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);

        $this->assertTrue(true);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
