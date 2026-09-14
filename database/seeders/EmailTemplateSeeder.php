<?php

namespace Database\Seeders;

use App\Enums\EmailRecipientType;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed Email Template registry (docs/ARCHITECTURE.md §16.5/§16.6)
 * from config('email_templates') — the single source of truth for which
 * keys/recipients exist. Idempotent (firstOrCreate on the unique
 * notification_key+recipient_type pair) so re-running it never duplicates
 * or clobbers an admin's already-edited copy beyond the seeded defaults on
 * a genuinely first run.
 *
 * A config entry may optionally set 'default_subject'/'default_html_body'/
 * 'default_text_body' to seed real, event-specific copy instead of the
 * generic placeholder below — used for keys where sensible default wording
 * is known up front. Each of those three may be a plain string (applied to
 * every recipient of that key) or an array keyed by recipient type
 * ('user'/'admin') for a key whose recipients need genuinely different
 * copy (e.g. contact_form_submitted's submitter receipt vs. admin alert).
 * Every key without a default falls back to the original generic
 * placeholder, completely unchanged from before.
 *
 * A key that gains real defaults *after* its row was already seeded with
 * the old generic placeholder (e.g. email_verification/password_reset,
 * whose original rows predate this default-content mechanism and were
 * missing their own action link — confirmed broken in production 2026-09-14)
 * needs those existing rows backfilled, not just new installs fixed —
 * firstOrCreate() alone never touches a row that already exists. backfill()
 * below does that, but only ever overwrites a row whose current
 * subject+html_body match the exact generic placeholder this class itself
 * would have generated — i.e. content no admin has ever actually edited.
 * Any row that differs at all, for any reason, is left completely alone.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('email_templates', []) as $key => $definition) {
            foreach ($definition['recipients'] as $recipient) {
                $recipientType = EmailRecipientType::from($recipient);

                $subject = $this->pick($definition['default_subject'] ?? null, $recipient)
                    ?? $this->defaultSubject($definition['label'], $recipientType);
                $htmlBody = $this->pick($definition['default_html_body'] ?? null, $recipient)
                    ?? $this->defaultHtmlBody($definition['label'], $recipientType);
                $textBody = $this->pick($definition['default_text_body'] ?? null, $recipient);

                $template = EmailTemplate::query()->firstOrCreate(
                    ['notification_key' => $key, 'recipient_type' => $recipient],
                    [
                        'is_enabled' => true,
                        'subject' => $subject,
                        'html_body' => $htmlBody,
                        'text_body' => $textBody,
                        'available_variables' => $definition['variables'],
                    ],
                );

                if (! $template->wasRecentlyCreated) {
                    $this->backfill($template, $definition, $recipientType, $subject, $htmlBody, $textBody);
                }
            }
        }
    }

    /**
     * Only overwrites a row that still exactly matches the generic
     * placeholder defaultSubject()/defaultHtmlBody() would have generated —
     * see class docblock. text_body is included in the "untouched" check
     * (the generic placeholder never sets one, so a non-null value there
     * already means an admin added one) but is itself backfilled alongside
     * subject/html_body once that check passes.
     */
    private function backfill(
        EmailTemplate $template,
        array $definition,
        EmailRecipientType $recipientType,
        string $newSubject,
        string $newHtmlBody,
        ?string $newTextBody,
    ): void {
        $stillGeneric = $template->subject === $this->defaultSubject($definition['label'], $recipientType)
            && $template->html_body === $this->defaultHtmlBody($definition['label'], $recipientType)
            && $template->text_body === null;

        $hasRealDefault = isset($definition['default_subject']) || isset($definition['default_html_body']);

        if (! $stillGeneric || ! $hasRealDefault) {
            return;
        }

        $template->subject = $newSubject;
        $template->html_body = $newHtmlBody;
        $template->text_body = $newTextBody;
        $template->save();
    }

    /**
     * A default_* config value is either a plain string (same content for
     * every recipient of that key) or an array keyed by recipient type
     * ('user'/'admin') for content that must differ per recipient.
     */
    private function pick(string|array|null $value, string $recipient): ?string
    {
        if (is_array($value)) {
            return $value[$recipient] ?? null;
        }

        return $value;
    }

    private function defaultSubject(string $label, EmailRecipientType $recipient): string
    {
        return $recipient === EmailRecipientType::Admin
            ? "[{{site_name}}] {$label}"
            : "{{site_name}} — {$label}";
    }

    private function defaultHtmlBody(string $label, EmailRecipientType $recipient): string
    {
        // Only {{site_name}} is common to every key's variable list (see
        // config/email_templates.php) — the rest differ per key, so the
        // seeded default body deliberately doesn't assume one exists.
        // Administrators customize the copy per key using that key's own
        // listed variables.
        return $recipient === EmailRecipientType::Admin
            ? "<p>{$label} on {{site_name}}.</p>"
            : "<p>{$label} — {{site_name}}.</p>";
    }
}
