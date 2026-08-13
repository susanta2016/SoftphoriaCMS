<?php

namespace App\Shared\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;

/**
 * A thin Mailable wrapper around already-rendered Email Template content
 * (TemplatedMailer::send()/renderAsMailable()) — the single Mailable type
 * every Email Template send goes through, so both the standalone-send path
 * and integration points that must return a Mailable (e.g.
 * Illuminate\Auth\Notifications\ResetPassword::toMailUsing() — see
 * AppServiceProvider::boot()) share one real, fake()-able Mailable class
 * instead of a raw Mail::send() call MailFake can't observe. Never
 * constructed with unsubstituted template content — the caller is
 * responsible for variable substitution before building this.
 */
class TemplatedNotificationMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        private readonly string $htmlBody,
        private readonly ?string $textBody = null,
        private readonly ?string $replyToEmail = null,
        private readonly ?string $replyToName = null,
    ) {}

    public function build(): static
    {
        $this->subject($this->subjectLine)->html($this->htmlBody);

        // Mailable::text() only accepts a Blade view name, but the
        // underlying renderer (Mailer::addContent()) accepts any Htmlable
        // for the same slot — setting $textView directly (rather than
        // through text()) is what lets a raw, already-rendered string be
        // used as the plain-text alternative without a view file.
        if (filled($this->textBody)) {
            $this->textView = new HtmlString($this->textBody);
        }

        if (filled($this->replyToEmail)) {
            $this->replyTo($this->replyToEmail, $this->replyToName ?: null);
        }

        return $this;
    }
}
