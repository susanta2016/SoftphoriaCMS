<?php

namespace App\Exceptions\Contact;

use RuntimeException;

/**
 * Thrown by App\Actions\Contact\SendContactSubmissionReplyAction when a
 * reply is refused or could not be sent. Messages are safe to show to the
 * acting admin — never the underlying mail error (which can name hosts or
 * credentials); that is only logged.
 */
class ContactSubmissionReplyException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('Replying to contact submissions is turned off.');
    }

    public static function notAllowed(): self
    {
        return new self('Only administrators can reply to contact submissions.');
    }

    public static function keyMismatch(): self
    {
        return new self('This reply form is out of date. Close it and open Reply again.');
    }

    public static function templateUnavailable(): self
    {
        return new self('The reply was not sent: the "Contact Form Reply (from Admin)" email template is missing or disabled.');
    }

    public static function sendFailed(): self
    {
        return new self('The reply could not be sent. Your message has been kept — try again, or check the mail settings.');
    }
}
