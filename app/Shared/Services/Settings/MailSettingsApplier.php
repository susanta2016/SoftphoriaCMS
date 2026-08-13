<?php

namespace App\Shared\Services\Settings;

/**
 * Applies the `email` settings group (Website Setup's Email tab —
 * docs/ARCHITECTURE.md §16.4) onto Laravel's own mail configuration at
 * send-time. No bespoke mail-provider abstraction is introduced — Phase 1
 * exposes exactly one admin-configurable provider (SMTP) through Laravel's
 * existing `config('mail.mailers.smtp')`/`config('mail.default')`/
 * `config('mail.from')` keys.
 *
 * Called immediately before mail is sent (TemplatedMailer::send(), the Send
 * Test Email action) rather than on every request, so a request that sends
 * no mail costs no extra `settings` lookup.
 */
class MailSettingsApplier
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function apply(): void
    {
        $this->applyFromArray($this->settings->all('email'));
    }

    /**
     * @param  array<string, mixed>  $email
     */
    public function applyFromArray(array $email): void
    {
        if (! ($email['enabled'] ?? false)) {
            config(['mail.default' => 'log']);

            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $this->schemeForEncryption($email['smtp_encryption'] ?? null),
            'mail.mailers.smtp.host' => $email['smtp_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $email['smtp_port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $email['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $email['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'mail.from.address' => $email['from_email'] ?? config('mail.from.address'),
            'mail.from.name' => $email['from_name'] ?? config('mail.from.name'),
        ]);
    }

    /**
     * Symfony Mailer (Laravel's SMTP transport) has no standalone
     * "encryption" setting — it is expressed as the connection scheme.
     * `ssl` (implicit TLS, typically port 465) maps to `smtps`; `tls`
     * (STARTTLS, typically port 587) and `none` both map to `smtp`, since
     * Symfony Mailer negotiates STARTTLS opportunistically on that scheme
     * and there is no separate "force plaintext" scheme to select.
     */
    private function schemeForEncryption(?string $encryption): ?string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }
}
