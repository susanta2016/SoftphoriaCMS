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
 * Every TemplatedMailer send is wrapped in the one shared, branded email
 * layout (logo header + footer), with the stored template supplying only
 * its own content, and always carries a plain-text part.
 */
class BrandedEmailLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);

        $settings = app(SettingsRepository::class);
        $settings->set('general', 'site_name', 'All The Things Light');
        $settings->set('general', 'site_url', 'https://allthethingslight.example');
    }

    public function test_templated_mail_is_wrapped_in_the_branded_header_and_footer(): void
    {
        $html = $this->render('email_verification', ['user_name' => 'Jane', 'verification_url' => 'https://allthethingslight.example/verify/abc'])->render();

        $this->assertStringContainsString('href="https://allthethingslight.example/verify/abc"', $html);
        $this->assertStringContainsString('href="https://allthethingslight.example/contact"', $html);
        $this->assertStringContainsString('A creative home for music, writing, reflection, thinking, and community.', $html);
        $this->assertStringContainsString('© '.now()->year.' All The Things Light. All rights reserved.', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_footer_uses_the_site_footer_settings_with_a_dynamic_year(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('footer', 'subheading', 'Light for every day.');
        $settings->set('footer', 'copyright_text', 'Copyright {year} ATTL');

        $html = $this->render('email_verification', ['user_name' => 'Jane'])->render();

        $this->assertStringContainsString('Light for every day.', $html);
        $this->assertStringContainsString('Copyright '.now()->year.' ATTL', $html);
        $this->assertStringNotContainsString('{year}', $html);
    }

    public function test_the_stored_template_holds_only_its_own_content(): void
    {
        foreach (EmailTemplate::all() as $template) {
            $this->assertStringNotContainsString('All rights reserved', $template->html_body);
            $this->assertStringNotContainsString('<html', $template->html_body);
        }
    }

    public function test_a_template_without_a_plain_text_fallback_gets_one_generated_from_its_html(): void
    {
        EmailTemplate::query()
            ->where(['notification_key' => 'email_verification', 'recipient_type' => 'user'])
            ->update([
                'html_body' => "Hi {{user_name}},\n\nPlease <a href=\"{{verification_url}}\">verify your email</a>.\nThanks &amp; welcome.",
                'text_body' => null,
            ]);

        $text = $this->textOf($this->render('email_verification', ['user_name' => 'Jane', 'verification_url' => 'https://x.test/v']));

        $this->assertStringStartsWith("Hi Jane,\n\nPlease verify your email: https://x.test/v.\nThanks & welcome.\n\n--\nAll The Things Light\n", $text);
        $this->assertStringContainsString('Contact: https://allthethingslight.example/contact', $text);
        $this->assertStringContainsString('© '.now()->year.' All The Things Light. All rights reserved.', $text);
    }

    public function test_html_to_text_keeps_link_urls_visible(): void
    {
        $text = BrandedEmailLayout::htmlToText('<p>Hi,</p><p><a href="https://x.test/a?b=1&amp;c=2">Open it</a></p><p><a href="https://x.test">https://x.test</a></p>');

        $this->assertSame("Hi,\n\nOpen it: https://x.test/a?b=1&c=2\n\nhttps://x.test", $text);
    }

    public function test_every_seeded_template_renders_inside_the_layout_with_a_text_part(): void
    {
        foreach (EmailTemplate::all() as $template) {
            $mail = $this->render($template->notification_key, [], $template->recipient_type);

            $this->assertStringContainsString('class="email-container"', $mail->render(), $template->notification_key);
            $this->assertStringContainsString('All rights reserved.', $this->textOf($mail), $template->notification_key);
        }
    }

    public function test_send_still_goes_through_the_templated_mailable(): void
    {
        Mail::fake();

        app(TemplatedMailer::class)->send('email_verification', EmailRecipientType::User, 'jane@example.com', ['user_name' => 'Jane']);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com')
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
