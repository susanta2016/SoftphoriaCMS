<?php

namespace App\Enums;

/**
 * What SubscribeToNewsletterAction actually did with a signup submission —
 * NewsletterController uses this to choose the flash message, since a
 * suppressed address (bounced/complained) must be told something different
 * from a fresh confirmation email, not silently swallowed the way the
 * honeypot path is (see the controller's own docblock).
 */
enum NewsletterSubscriptionOutcome
{
    case ConfirmationSent;
    case AlreadySubscribed;
    case Suppressed;
}
