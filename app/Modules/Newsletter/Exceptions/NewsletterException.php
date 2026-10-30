<?php

namespace App\Modules\Newsletter\Exceptions;

use App\Modules\Newsletter\Enums\CampaignStatus;
use RuntimeException;

/**
 * A Newsletter Marketing operation was refused. Messages are written for
 * the acting admin and are safe to show in a notification — provider/SMTP
 * errors are logged, never put in one of these.
 */
class NewsletterException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('Newsletter Marketing is turned off (NEWSLETTER_MARKETING_ENABLED).');
    }

    public static function invalidTransition(CampaignStatus $from, CampaignStatus $to): self
    {
        return new self("A {$from->getLabel()} campaign can't be changed to {$to->getLabel()}.");
    }

    public static function notEditable(): self
    {
        return new self('Only draft campaigns can be edited. Unschedule it first, or duplicate it.');
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
