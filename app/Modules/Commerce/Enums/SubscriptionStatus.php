<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mirrors Stripe's own Subscription status vocabulary verbatim, so webhook
 * handlers map 1:1 without translating — see Subscription::isActive() for
 * the one business rule ("active" plus a current_period_end grace check)
 * built on top of this.
 */
enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::IncompleteExpired => 'Incomplete (Expired)',
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Canceled => 'Canceled',
            self::Unpaid => 'Unpaid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active, self::Trialing => 'success',
            self::PastDue, self::Unpaid => 'warning',
            self::Incomplete => 'gray',
            self::IncompleteExpired, self::Canceled => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
