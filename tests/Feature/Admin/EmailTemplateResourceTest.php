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
            // Counted, not assertCanSeeTableRecords(): there are more keys
            // than the list's 10-per-page default, so later rows sit on page 2.
            ->assertCountTableRecords(EmailTemplate::query()->where('recipient_type', 'user')->count());
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
                && str_contains($mail->subjectLine, 'Verify Your Email Address');
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

    /**
     * TemplatedMailer::formatHtmlBody() — the "HTML Body" field
     * (EditEmailTemplate) is a plain Textarea, not a rich-text editor, so an
     * admin's ordinary blank-line-separated paragraphs are literal newlines
     * with no markup around them; inserted directly as HTML those collapse
     * to one run-together block per the HTML whitespace spec. These tests
     * cover the fix at the TemplatedMailer level (what a real send
     * produces); test_the_filament_preview_renders_the_same_paragraph_formatting
     * below covers the admin-facing preview going through the identical
     * function.
     */
    public function test_multiple_paragraphs_are_rendered_as_separate_paragraph_tags(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => "Paragraph one.\n\nParagraph two.\n\nParagraph three."]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $rendered = $mailable->render();

        $this->assertStringNotContainsString('Paragraph one. Paragraph two. Paragraph three.', $rendered);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">Paragraph one.</p>', $rendered);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">Paragraph two.</p>', $rendered);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">Paragraph three.</p>', $rendered);
    }

    public function test_a_single_line_break_within_a_paragraph_becomes_a_br_tag(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => "Line one\nLine two"]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $rendered = $mailable->render();

        $this->assertStringContainsString("Line one<br />\nLine two", $rendered);
    }

    public function test_multiple_blank_lines_between_paragraphs_still_produce_two_paragraphs(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => "First.\n\n\n\nSecond."]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $rendered = $mailable->render();

        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">First.</p>', $rendered);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">Second.</p>', $rendered);
    }

    public function test_empty_html_body_is_handled_safely(): void
    {
        $this->assertSame('', TemplatedMailer::formatHtmlBody(''));
        $this->assertSame('', TemplatedMailer::formatHtmlBody("\n\n   \n"));
    }

    /**
     * The exact string EmailTemplateSeeder::defaultHtmlBody() produces for
     * every key's initial seeded row — proves a freshly seeded, never-yet-
     * admin-edited template is not touched/double-wrapped by this fix.
     */
    public function test_the_seeders_own_default_html_body_is_not_double_wrapped(): void
    {
        $seededDefault = '<p>Verify Email — {{site_name}}.</p>';

        $this->assertSame($seededDefault, TemplatedMailer::formatHtmlBody($seededDefault));
    }

    public function test_existing_block_level_html_is_left_completely_untouched(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $handAuthored = '<div><p>Already structured.</p><p>Second paragraph.</p></div>';
        $template->update(['html_body' => $handAuthored]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $rendered = $mailable->render();

        $this->assertStringContainsString($handAuthored, $rendered);
    }

    /**
     * Reproduces the exact production bug: the "New Registration / Welcome"
     * template's real content had its first two paragraphs hand-wrapped in
     * <p> but the rest of the body (a plain-text bullet list and closing
     * paragraphs) was still plain text relying on formatHtmlBody(). The
     * original all-or-nothing bypass ("skip everything if a <p> appears
     * anywhere") meant that one pre-wrapped paragraph silently disabled
     * formatting for every plain-text paragraph after it, collapsing the
     * rest of the email into one unreadable block. formatHtmlBody() now
     * judges each paragraph independently.
     */
    public function test_a_mix_of_pre_wrapped_and_plain_text_paragraphs_are_each_formatted_correctly(): void
    {
        $mixed = "<p>Already wrapped paragraph.</p>\n\nA second, plain-text paragraph that should also get wrapped.\n\nA third plain paragraph.";

        $result = TemplatedMailer::formatHtmlBody($mixed);

        $this->assertStringContainsString('<p>Already wrapped paragraph.</p>', $result);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">A second, plain-text paragraph that should also get wrapped.</p>', $result);
        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">A third plain paragraph.</p>', $result);
        $this->assertStringNotContainsString('A second, plain-text paragraph that should also get wrapped. A third plain paragraph.', $result);
    }

    public function test_an_inline_link_inside_a_paragraph_still_gets_paragraph_formatting(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => 'Please <a href="{{verification_url}}">click here</a> to verify.']);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, [
            'user_name' => 'Jane',
            'verification_url' => 'https://example.com/verify',
        ]);
        $rendered = $mailable->render();

        $this->assertStringContainsString('<p style="margin:0 0 1em 0;">Please <a href="https://example.com/verify">click here</a> to verify.</p>', $rendered);
    }

    public function test_the_plain_text_fallback_keeps_its_own_line_breaks_with_no_html_inserted(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['text_body' => "Paragraph one.\n\nParagraph two."]);

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $mailable->build();

        $this->assertSame("Paragraph one.\n\nParagraph two.", (string) $mailable->textView);
    }

    /**
     * The preview is now embedded in a sandboxed iframe (srcdoc) so it
     * renders using only TemplatedMailer::renderEmailHtml()'s own layout
     * CSS, never Filament's admin/Tailwind styles (see EditEmailTemplate::
     * renderPreviewBody()'s own docblock) — the srcdoc attribute value is
     * necessarily HTML-escaped, so this asserts against the escaped form
     * rather than literal unescaped markup.
     */
    public function test_the_filament_preview_renders_the_same_paragraph_formatting_as_a_real_send(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();

        $component = Livewire::actingAs($this->admin())
            ->test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['html_body' => "First paragraph.\n\nSecond paragraph."]);

        $component->assertSeeHtml('<iframe srcdoc="');

        $html = $component->html();
        $this->assertStringContainsString(e('<p style="margin:0 0 1em 0;">First paragraph.</p>'), $html);
        $this->assertStringContainsString(e('<p style="margin:0 0 1em 0;">Second paragraph.</p>'), $html);
    }

    /**
     * Proves the preview and a real send share the exact same
     * TemplatedMailer::renderEmailHtml() output — the shared email layout
     * (background, centered container, typography) appears in the preview,
     * not just the paragraph formatting.
     */
    public function test_the_filament_preview_uses_the_same_shared_layout_as_a_real_send(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where('notification_key', 'email_verification')->where('recipient_type', 'user')->firstOrFail();
        $template->update(['html_body' => 'Hello there.']);

        $component = Livewire::actingAs($this->admin())->test(EditEmailTemplate::class, ['record' => $template->getRouteKey()]);
        $previewHtml = $component->html();

        $mailable = app(TemplatedMailer::class)->renderAsMailable('email_verification', EmailRecipientType::User, ['user_name' => 'Jane']);
        $sentHtml = $mailable->render();

        foreach (['<!DOCTYPE html>', 'class="email-container"', 'email-padding'] as $layoutMarker) {
            $this->assertStringContainsString(e($layoutMarker), $previewHtml, "Preview is missing layout marker: {$layoutMarker}");
            $this->assertStringContainsString($layoutMarker, $sentHtml, "Sent email is missing layout marker: {$layoutMarker}");
        }
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
