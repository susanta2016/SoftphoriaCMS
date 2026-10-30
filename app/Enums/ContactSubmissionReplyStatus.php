<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An admin reply's send state (contact_submission_replies.status). Mail is
 * sent synchronously, so Sent means the mail server accepted the message —
 * not that it reached the inbox (no delivery tracking exists). Pending is
 * only ever seen while a send is in flight, or if the request died mid-send.
 */
enum ContactSubmissionReplyStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Sending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
