<?php

namespace App\Modules\Newsletter\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email;

/**
 * One rendered campaign (or test) email. Already-personalized HTML/text —
 * nothing here is a Blade template. Adds the bulk-mail headers:
 * List-Unsubscribe + List-Unsubscribe-Post (RFC 8058 one-click, required
 * by Gmail/Yahoo for bulk senders), a Message-ID unique to the recipient,
 * and, for SES, the configuration set and a message tag carrying the
 * recipient token so events can be matched even without the provider's
 * message id.
 */
class NewsletterCampaignMail extends Mailable
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $htmlBody,
        public readonly string $textBody,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?string $oneClickUnsubscribeUrl = null,
        public readonly ?string $messageIdentifier = null,
        public readonly array $extraHeaders = [],
    ) {}

    public function build(): static
    {
        $this->subject($this->subjectLine)
            ->from($this->fromAddress, $this->fromName)
            ->html($this->htmlBody);

        $this->textView = new HtmlString($this->textBody);

        $this->withSymfonyMessage(function (Email $message): void {
            $headers = $message->getHeaders();

            if ($this->messageIdentifier !== null) {
                $headers->addIdHeader('Message-ID', $this->messageIdentifier);
            }

            if ($this->oneClickUnsubscribeUrl !== null) {
                $headers->addTextHeader('List-Unsubscribe', '<'.$this->oneClickUnsubscribeUrl.'>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }

            foreach ($this->extraHeaders as $name => $value) {
                $headers->addTextHeader($name, $value);
            }
        });

        return $this;
    }
}
