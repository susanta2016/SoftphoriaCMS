<?php

namespace App\Modules\Commerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentTransactionType: string implements HasColor, HasLabel
{
    case Charge = 'charge';
    case Refund = 'refund';
    case SubscriptionInvoicePaid = 'subscription_invoice_paid';
    case SubscriptionInvoiceFailed = 'subscription_invoice_failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Charge => 'Charge',
            self::Refund => 'Refund',
            self::SubscriptionInvoicePaid => 'Subscription Invoice Paid',
            self::SubscriptionInvoiceFailed => 'Subscription Invoice Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Charge, self::SubscriptionInvoicePaid => 'success',
            self::Refund => 'warning',
            self::SubscriptionInvoiceFailed => 'danger',
        };
    }
}
