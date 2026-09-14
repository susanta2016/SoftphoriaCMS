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
 * 'default_text_body' (plain strings, not per-recipient) to seed real,
 * event-specific copy instead of the generic placeholder below — used for
 * newer keys where sensible default wording is known up front. Every key
 * without those keys falls back to the original generic placeholder,
 * completely unchanged from before.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('email_templates', []) as $key => $definition) {
            foreach ($definition['recipients'] as $recipient) {
                EmailTemplate::query()->firstOrCreate(
                    ['notification_key' => $key, 'recipient_type' => $recipient],
                    [
                        'is_enabled' => true,
                        'subject' => $definition['default_subject']
                            ?? $this->defaultSubject($definition['label'], EmailRecipientType::from($recipient)),
                        'html_body' => $definition['default_html_body']
                            ?? $this->defaultHtmlBody($definition['label'], EmailRecipientType::from($recipient)),
                        'text_body' => $definition['default_text_body'] ?? null,
                        'available_variables' => $definition['variables'],
                    ],
                );
            }
        }
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
