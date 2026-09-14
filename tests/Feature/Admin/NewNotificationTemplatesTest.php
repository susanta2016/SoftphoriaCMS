<?php

namespace Tests\Feature\Admin;

use App\Enums\EmailRecipientType;
use App\Models\EmailTemplate;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four notification keys added for Light Post / Gratitude Journal /
 * Inspirational Resource acknowledgements — covers exactly the gap the
 * read-only audit found: the Actions and config already referenced these
 * keys, but no EmailTemplate row existed for any of them until
 * EmailTemplateSeeder was re-run. Delivery itself (who gets emailed, when)
 * is covered by LightPostAcknowledgementTest / GratitudeJournalAcknowledgementTest
 * / InspirationalResourceSubmissionTest / ResourceSubmissionTest — this file
 * covers the template records and the shared rendering architecture instead.
 */
class NewNotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW_KEYS = [
        'light_post_submitted',
        'gratitude_journal_submitted',
        'inspirational_resource_pending',
        'inspirational_resource_published',
    ];

    public function test_seeding_creates_exactly_one_user_record_per_new_key(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (self::NEW_KEYS as $key) {
            $this->assertSame(
                1,
                EmailTemplate::query()->where('notification_key', $key)->count(),
                "Expected exactly one EmailTemplate row for {$key}.",
            );

            $record = EmailTemplate::query()->where('notification_key', $key)->firstOrFail();
            $this->assertSame('user', $record->recipient_type->value, "{$key} should be recipient_type=user, not admin.");
            $this->assertTrue($record->is_enabled, "{$key} should be enabled by default.");
        }
    }

    public function test_re_seeding_does_not_create_duplicates_or_touch_existing_rows(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $before = EmailTemplate::query()->where('notification_key', 'inspirational_resource_submitted')->firstOrFail();

        $this->seed(EmailTemplateSeeder::class);

        foreach (self::NEW_KEYS as $key) {
            $this->assertSame(1, EmailTemplate::query()->where('notification_key', $key)->count());
        }

        $after = EmailTemplate::query()->where('notification_key', 'inspirational_resource_submitted')->firstOrFail();
        $this->assertEquals($before->updated_at, $after->updated_at);
    }

    public function test_templated_mailer_resolves_each_new_template(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $mailer = app(TemplatedMailer::class);

        $this->assertNotNull($mailer->renderAsMailable('light_post_submitted', EmailRecipientType::User, ['user_name' => 'Jane', 'light_post_url' => 'https://example.com/light']));
        $this->assertNotNull($mailer->renderAsMailable('gratitude_journal_submitted', EmailRecipientType::User, ['user_name' => 'Jane', 'visibility_label' => 'Public', 'journal_url' => 'https://example.com/journal']));
        $this->assertNotNull($mailer->renderAsMailable('inspirational_resource_pending', EmailRecipientType::User, ['submitter_name' => 'Jane', 'subject' => 'My Story']));
        $this->assertNotNull($mailer->renderAsMailable('inspirational_resource_published', EmailRecipientType::User, ['submitter_name' => 'Jane', 'subject' => 'My Story', 'resource_url' => 'https://example.com/resource']));
    }

    public function test_each_new_templates_variables_are_substituted_in_the_rendered_email(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $mailer = app(TemplatedMailer::class);

        $cases = [
            'light_post_submitted' => [
                'variables' => ['user_name' => 'Jane Doe', 'light_post_url' => 'https://example.com/light-posts/abc'],
                'expect' => ['Jane Doe', 'https://example.com/light-posts/abc'],
            ],
            'gratitude_journal_submitted' => [
                'variables' => ['user_name' => 'Jane Doe', 'visibility_label' => 'Private', 'journal_url' => 'https://example.com/journal'],
                'expect' => ['Jane Doe', 'Private', 'https://example.com/journal'],
            ],
            'inspirational_resource_pending' => [
                'variables' => ['submitter_name' => 'Jane Doe', 'subject' => 'A Very Specific Story Title'],
                'expect' => ['Jane Doe', 'A Very Specific Story Title'],
            ],
            'inspirational_resource_published' => [
                'variables' => ['submitter_name' => 'Jane Doe', 'subject' => 'A Very Specific Story Title', 'resource_url' => 'https://example.com/resources/abc'],
                'expect' => ['Jane Doe', 'A Very Specific Story Title', 'https://example.com/resources/abc'],
            ],
        ];

        foreach ($cases as $key => $case) {
            $mailable = $mailer->renderAsMailable($key, EmailRecipientType::User, $case['variables']);
            $this->assertNotNull($mailable);
            $rendered = $mailable->render();

            foreach ($case['expect'] as $expected) {
                $this->assertStringContainsString($expected, $rendered, "{$key}: expected \"{$expected}\" to appear in the rendered email.");
            }

            // No unsubstituted {{token}} should ever survive into a real send.
            $this->assertDoesNotMatchRegularExpression('/\{\{\s*[a-z_]+\s*\}\}/', $rendered, "{$key}: an unsubstituted {{token}} was left in the rendered email.");
        }
    }

    /**
     * Confirms the shared layout (Part 3/4 of the implementation) is
     * actually present in a real send for each new key — not just that
     * *some* HTML is produced.
     */
    public function test_each_new_templates_rendered_email_uses_the_shared_layout_structure(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $mailer = app(TemplatedMailer::class);

        foreach (self::NEW_KEYS as $key) {
            $mailable = $mailer->renderAsMailable($key, EmailRecipientType::User, [
                'user_name' => 'Jane',
                'submitter_name' => 'Jane',
                'subject' => 'Sample',
                'light_post_url' => 'https://example.com',
                'journal_url' => 'https://example.com',
                'resource_url' => 'https://example.com',
                'visibility_label' => 'Public',
            ]);
            $rendered = $mailable->render();

            $this->assertStringContainsString('<!DOCTYPE html>', $rendered, "{$key}: missing outer document structure.");
            $this->assertStringContainsString('class="email-container"', $rendered, "{$key}: missing the centered content container.");
            $this->assertStringContainsString('max-width:600px', $rendered, "{$key}: missing the sensible maximum width.");
            $this->assertStringContainsString('email-padding', $rendered, "{$key}: missing consistent horizontal padding.");
        }
    }
}
