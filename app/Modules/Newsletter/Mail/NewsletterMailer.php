<?php

namespace App\Modules\Newsletter\Mail;

use App\Modules\Newsletter\Support\NewsletterFeature;
use App\Shared\Services\Settings\MailSettingsApplier;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one NewsletterCampaignMail through the configured campaign mailer
 * (config('newsletter.mailer')): a named Laravel mailer such as the
 * dedicated "newsletter" SMTP connection or "ses", or — when unset — the
 * site's own Settings > Email configuration, applied the same way
 * transactional mail applies it. Refuses outright when the module is off,
 * whoever calls it.
 */
class NewsletterMailer
{
    public function __construct(private readonly MailSettingsApplier $mailSettings) {}

    public function mailerName(): string
    {
        $configured = config('newsletter.mailer');

        if (filled($configured)) {
            return (string) $configured;
        }

        $this->mailSettings->apply();

        return (string) config('mail.default');
    }

    /**
     * Returns the provider's message id when it reported one (SES SMTP
     * and the SES API both do), otherwise the Message-ID header.
     */
    public function send(string $to, NewsletterCampaignMail $mail): ?string
    {
        NewsletterFeature::ensureEnabled();

        $sent = Mail::mailer($this->mailerName())->to($to)->send($mail);

        return $sent?->getMessageId() ?: null;
    }
}
