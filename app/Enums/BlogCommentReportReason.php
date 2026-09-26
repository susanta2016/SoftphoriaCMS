<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BlogCommentReportReason: string implements HasLabel
{
    case Spam = 'spam';
    case Abusive = 'abusive';
    case OffTopic = 'off_topic';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising',
            self::Abusive => 'Abusive or hateful',
            self::OffTopic => 'Off-topic',
            self::Other => 'Something else',
        };
    }
}
