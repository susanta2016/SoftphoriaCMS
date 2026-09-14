<?php

namespace Tests\Feature\Admin;

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the 2026-09-14 production bug: email_verification's
 * seeded default body never referenced {{verification_url}} at all (the
 * seeder's generic placeholder only ever echoes the label + {{site_name}}),
 * so the "resend verification email" feature — both the admin row action
 * and the public register/free/thank-you form — sent a real email that
 * contained no way to actually verify. password_reset and several other
 * keys had the exact same defect. Fixed by giving every link/action-bearing
 * key real default content in config/email_templates.php. This test
 * ensures every key whose 'variables' list contains something the
 * recipient must act on (an *_url variable, or — for the two admin-only
 * submission-notice keys — the actual submitted data) is never again
 * seeded without it.
 */
class NotificationTemplatesContainActionLinksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * notification_key => [recipient_type => variables that must appear].
     * Only the variables essential to the email actually doing its job —
     * not every variable a key lists (a missing {{user_name}} salutation is
     * a copy-quality nit, not a broken feature).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const array REQUIRED_VARIABLES = [
        'email_verification' => ['user' => ['verification_url']],
        'password_reset' => ['user' => ['reset_url']],
        'review_published' => ['user' => ['review_url']],
        'gratitude_journal_reminder' => ['user' => ['journal_url']],
        'contact_form_submitted' => ['admin' => ['name', 'email', 'message']],
        'inspirational_resource_submitted' => ['admin' => ['submitter_name', 'submitter_email', 'subject']],
    ];

    public function test_every_link_or_action_bearing_template_contains_its_required_variables(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (self::REQUIRED_VARIABLES as $key => $byRecipient) {
            foreach ($byRecipient as $recipient => $variables) {
                $template = EmailTemplate::query()
                    ->where('notification_key', $key)
                    ->where('recipient_type', $recipient)
                    ->first();

                $this->assertNotNull($template, "Expected an EmailTemplate row for {$key}/{$recipient}.");

                foreach ($variables as $variable) {
                    $this->assertStringContainsString(
                        "{{{$variable}}}",
                        $template->html_body,
                        "{$key}/{$recipient}: html_body is missing {{{$variable}}} — the email can't do its job without it.",
                    );
                }
            }
        }
    }

    /**
     * Re-running the seeder against a row already holding real (non-generic)
     * content — whether admin-edited or already backfilled — must never
     * revert it back toward the generic placeholder.
     */
    public function test_re_seeding_does_not_alter_already_customized_rows(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $before = EmailTemplate::query()->where('notification_key', 'email_verification')->first();
        $before->html_body = '<p>Hi {{user_name}}, a real admin wrote this. {{verification_url}}</p>';
        $before->save();

        $this->seed(EmailTemplateSeeder::class);

        $after = EmailTemplate::query()->where('notification_key', 'email_verification')->first();
        $this->assertSame($before->html_body, $after->html_body);
    }
}
