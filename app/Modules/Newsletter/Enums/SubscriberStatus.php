<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Labels for newsletter_subscribers.status. The stored values are the ones
 * the footer signup and SES webhook already write (pending, subscribed,
 * unsubscribed, bounced, complained) plus suppressed/invalid, added by this
 * module. The column stays a plain string so existing code keeps comparing
 * the same literals. "Active" is `subscribed`; bounced/complained/
 * suppressed/invalid are the suppressed flavours. Only `subscribed` is ever
 * sendable (NewsletterSubscriber::scopeSendable()).
 */
enum SubscriberStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Suppressed = 'suppressed';
    case Invalid = 'invalid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending confirmation',
            self::Subscribed => 'Active',
            self::Unsubscribed => 'Unsubscribed',
            self::Bounced => 'Suppressed (hard bounce)',
            self::Complained => 'Suppressed (spam complaint)',
            self::Suppressed => 'Suppressed (by admin)',
            self::Invalid => 'Invalid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Subscribed => 'success',
            self::Pending => 'warning',
            self::Unsubscribed => 'gray',
            self::Bounced, self::Complained, self::Suppressed, self::Invalid => 'danger',
        };
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->getLabel() ?? (string) $value;
    }

    public static function colorFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->getColor() ?? 'gray';
    }
}
