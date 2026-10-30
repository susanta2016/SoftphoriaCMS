<?php

namespace App\Modules\Newsletter\Support;

use App\Shared\Services\Settings\SettingsRepository;

/**
 * Newsletter Settings (the `newsletter` group in the shared settings table,
 * edited on the Newsletter Settings admin page). Defaults only — the
 * module on/off switch is never stored here (see NewsletterFeature).
 */
class NewsletterSettings
{
    public const GROUP = 'newsletter';

    public const DEFAULT_FOOTER = <<<'HTML'
        <p style="margin:0 0 6px 0;">You are receiving this email because you subscribed to updates from {{ site_name }}.</p>
        <p style="margin:0 0 6px 0;">{{ sender.address }}</p>
        <p style="margin:0;"><a href="{{ unsubscribe_url }}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a> from these emails.</p>
        HTML;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function fromName(): string
    {
        return (string) ($this->settings->get(self::GROUP, 'from_name')
            ?: config('newsletter.from.name')
            ?: $this->settings->get('general', 'site_name', config('app.name')));
    }

    public function fromEmail(): string
    {
        return (string) ($this->settings->get(self::GROUP, 'from_email')
            ?: config('newsletter.from.address')
            ?: $this->settings->get('email', 'from_email')
            ?: config('mail.from.address'));
    }

    public function timezone(): string
    {
        $timezone = (string) $this->settings->get(self::GROUP, 'timezone', '');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('newsletter.default_timezone');
    }

    public function testRecipient(): ?string
    {
        return $this->settings->get(self::GROUP, 'test_recipient') ?: null;
    }

    /**
     * Never above config('newsletter.max_rate_per_second'), the hard
     * ceiling set from the provider's own limit.
     */
    public function ratePerSecond(): int
    {
        $ceiling = max(1, (int) config('newsletter.max_rate_per_second'));
        $configured = (int) $this->settings->get(self::GROUP, 'rate_per_second', $ceiling);

        return max(1, min($configured, $ceiling));
    }

    public function footerHtml(): string
    {
        return (string) ($this->settings->get(self::GROUP, 'footer_html') ?: self::DEFAULT_FOOTER);
    }

    /**
     * The postal address/identity line shown in the footer — commercial
     * email law (e.g. CAN-SPAM) expects a physical address.
     */
    public function senderAddress(): string
    {
        return (string) $this->settings->get(self::GROUP, 'sender_address', '');
    }

    public function trackOpensByDefault(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'track_opens', false);
    }

    public function trackClicksByDefault(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'track_clicks', false);
    }
}
