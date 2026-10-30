<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why an address is on the global suppression list. Unsubscribed is the
 * only reason lifted automatically (the subscriber re-confirming through
 * double opt-in); the rest only by an admin's explicit, audited action.
 */
enum SuppressionReason: string implements HasLabel
{
    case Unsubscribed = 'unsubscribed';
    case HardBounce = 'hard_bounce';
    case Complaint = 'complaint';
    case Invalid = 'invalid';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unsubscribed => 'Unsubscribed',
            self::HardBounce => 'Hard bounce',
            self::Complaint => 'Spam complaint',
            self::Invalid => 'Invalid address',
            self::Admin => 'Suppressed by admin',
        };
    }

    /**
     * The subscriber status that goes with this suppression.
     */
    public function subscriberStatus(): SubscriberStatus
    {
        return match ($this) {
            self::Unsubscribed => SubscriberStatus::Unsubscribed,
            self::HardBounce => SubscriberStatus::Bounced,
            self::Complaint => SubscriberStatus::Complained,
            self::Invalid => SubscriberStatus::Invalid,
            self::Admin => SubscriberStatus::Suppressed,
        };
    }
}
