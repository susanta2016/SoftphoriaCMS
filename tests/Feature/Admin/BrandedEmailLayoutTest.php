<?php

namespace Tests\Feature\Admin;

use App\Enums\EmailRecipientType;
use App\Models\EmailTemplate;
use App\Shared\Mail\BrandedEmailLayout;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Tests\TestCase;

/**
 * EMAIL-001 — every TemplatedMailer send is wrapped in the one shared
 * Softphoria layout, with the stored template supplying only its own
 * content, and always carries a plain-text part.
 */
class BrandedEmailLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_templated_mail_is_wrapped_in_the_shared_branded_layout(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('general', 'site_name', 'Softphoria');
        $settings->set('general', 'tagline', 'Be your tech partner');
        $settings->set('general', 'site_url', 'https://softphoria.example');

        $mail = $this->render('email_verification', ['user_name' => 'Jane', 'verification_url' => 'https://softphoria.example/verify/abc']);
        $html = $mail->render();

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('Hi Jane,', $html);
        $this->assertStringContainsString('href="https://softphoria.example/verify/abc"', $html);
        $this->assertStringContainsString('Be your tech partner', $html);
        $this->assertStringContainsString('href="https://softphoria.example/contact"', $html);
        $this->assertStringContainsString('&copy; '.now()->format('Y').' Softphoria. All rights reserved.', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_stored_template_holds_only_its_own_content(): void
    {
        $template = EmailTemplate::query()->where(['notification_key' => 'email_verification', 'recipient_type' => 'user'])->sole();

        $this->assertStringNotContainsString('All rights reserved', $template->html_body);
        $this->assertStringNotContainsString('<html', $template->html_body);
    }

    public function test_the_templates_own_plain_text_fallback_is_used_inside_the_text_layout(): void
    {
        $text = $this->textOf($this->render('password_reset', ['user_name' => 'Jane', 'reset_url' => 'https://softphoria.example/reset/xyz']));

        $this->assertStringStartsWith('Hi Jane,', $text);
        $this->assertStringContainsString('Reset my password: https://softphoria.example/reset/xyz', $text);
        $this->assertStringContainsString('All rights reserved.', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function test_a_template_without_a_plain_text_fallback_gets_one_generated_from_its_html(): void
    {
        EmailTemplate::query()
            ->where(['notification_key' => 'contact_form_submitted', 'recipient_type' => 'admin'])
            ->update(['text_body' => null]);

        $text = $this->textOf($this->render('contact_form_submitted', [
            'name' => 'Sam <b>Lee</b>',
            'email' => 'sam@example.com',
            'message' => "Line one\nLine two & more",
        ], EmailRecipientType::Admin));

        $this->assertStringContainsString('Name: Sam <b>Lee</b>', $text);
        $this->assertStringContainsString("Line one\nLine two & more", $text);
        $this->assertStringNotContainsString('<br>', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }

    public function test_html_to_text_keeps_link_urls_visible(): void
    {
        $text = BrandedEmailLayout::htmlToText('<p>Hi,</p><p><a href="https://x.test/a?b=1&amp;c=2" class="button">Open it</a></p><p><a href="https://x.test">https://x.test</a></p>');

        $this->assertSame("Hi,\n\nOpen it: https://x.test/a?b=1&c=2\n\nhttps://x.test", $text);
    }

    public function test_visitor_input_is_still_escaped_inside_the_layout(): void
    {
        $html = $this->render('contact_form_submitted', ['name' => '<a href="https://evil.test">x</a>'], EmailRecipientType::Admin)->render();

        $this->assertStringNotContainsString('href="https://evil.test"', $html);
        $this->assertStringContainsString('&lt;a href=', $html);
    }

    public function test_seeder_upgrades_an_unedited_previous_verification_body_to_the_button_version(): void
    {
        $template = EmailTemplate::query()->where(['notification_key' => 'email_verification', 'recipient_type' => 'user'])->sole();

        $template->forceFill(['html_body' => config('email_templates.email_verification.previous_default_html_body')])->save();
        $this->seed(EmailTemplateSeeder::class);
        $this->assertStringContainsString('class="button"', $template->fresh()->html_body);

        $template->forceFill(['html_body' => '<p>Custom {{verification_url}}</p>'])->save();
        $this->seed(EmailTemplateSeeder::class);
        $this->assertSame('<p>Custom {{verification_url}}</p>', $template->fresh()->html_body);
    }

    public function test_send_still_goes_through_the_templated_mailable(): void
    {
        Mail::fake();

        app(TemplatedMailer::class)->send('newsletter_subscribed', EmailRecipientType::User, 'sub@example.com', ['subscriber_email' => 'sub@example.com']);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('sub@example.com')
            && str_contains($mail->render(), 'All rights reserved.'));
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $key, array $variables, EmailRecipientType $recipient = EmailRecipientType::User): TemplatedNotificationMail
    {
        $mail = app(TemplatedMailer::class)->renderAsMailable($key, $recipient, $variables);
        $this->assertNotNull($mail);

        return $mail;
    }

    private function textOf(TemplatedNotificationMail $mail): string
    {
        return (string) (new ReflectionProperty($mail, 'textBody'))->getValue($mail);
    }
}
