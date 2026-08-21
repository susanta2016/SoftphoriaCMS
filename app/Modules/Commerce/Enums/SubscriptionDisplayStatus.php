<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Not a database column — computed by Subscription::displayStatus() from
 * `status` + `cancel_at_period_end` + `current_period_end`, the same
 * "never a stored, driftable duplicate of derivable state" approach as
 * Entitlement's EntitlementStatus. Exists specifically so Admin can tell
 * "Active" apart from "Active, but will not renew" at a glance — the raw
 * Stripe `status` alone can't distinguish those (both are `active`).
 */
enum SubscriptionDisplayStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case CancelingAtPeriodEnd = 'canceling_at_period_end';
    case Expired = 'expired';
    case PaymentProblem = 'payment_problem';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::CancelingAtPeriodEnd => 'Active — Cancels at Period End',
            self::Expired => 'Expired / Inactive',
            self::PaymentProblem => 'Payment / Renewal Problem',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::CancelingAtPeriodEnd => 'warning',
            self::Expired => 'gray',
            self::PaymentProblem => 'danger',
        };
    }
}
