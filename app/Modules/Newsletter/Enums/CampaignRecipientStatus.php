<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * One recipient's delivery state. "Sent" only means the provider accepted
 * the message; "Delivered" is set solely by a provider delivery event (SES
 * via /webhooks/ses). Without provider events configured a recipient stays
 * at Sent — it is never assumed Delivered.
 */
enum CampaignRecipientStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /**
     * Accepted by the provider, whatever happened after.
     *
     * @return array<int, string>
     */
    public static function submittedValues(): array
    {
        return [self::Sent->value, self::Delivered->value, self::Bounced->value, self::Complained->value];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Sending => 'Sending',
            self::Sent => 'Sent (submitted)',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
            self::Complained => 'Spam complaint',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Sending => 'warning',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Bounced, self::Complained, self::Failed => 'danger',
            self::Skipped, self::Cancelled => 'gray',
        };
    }
}
