<?php

namespace App\Modules\Newsletter\Support;

/**
 * Checks a campaign's From address against the sender domains verified
 * with the delivery provider (NEWSLETTER_ALLOWED_SENDER_DOMAINS). The app
 * can't ask the provider itself without its API SDK, so that variable is
 * the declaration of what was verified (SES "verified identities"); with
 * it empty, only the default sender's own domain is allowed.
 */
class SenderIdentity
{
    public function __construct(private readonly NewsletterSettings $settings) {}

    /**
     * @return array<int, string>
     */
    public function allowedDomains(): array
    {
        $domains = config('newsletter.allowed_sender_domains', []);

        if ($domains === []) {
            $default = self::domainOf($this->settings->fromEmail());
            $domains = $default !== null ? [$default] : [];
        }

        return array_values(array_unique(array_map('mb_strtolower', $domains)));
    }

    /**
     * Null when allowed, otherwise the reason it isn't.
     */
    public function validate(string $fromEmail): ?string
    {
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return 'The sender email address is not valid.';
        }

        $allowed = $this->allowedDomains();

        if ($allowed === []) {
            return 'No verified sender domain is configured (NEWSLETTER_ALLOWED_SENDER_DOMAINS).';
        }

        if (! in_array(self::domainOf($fromEmail), $allowed, true)) {
            return 'The sender must use a verified domain: '.implode(', ', $allowed).'.';
        }

        return null;
    }

    private static function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');

        return $at === false ? null : mb_strtolower(substr($email, $at + 1));
    }
}
