<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A member's Gratitude Journal email reminder cadence — stored as
 * `gratitude_reminder_frequency` inside the existing UserPreference.preferences
 * JSON blob (Gratitude Journal audit §7: reuse that column, no new table or
 * database column), read by SendGratitudeJournalRemindersCommand.
 */
enum GratitudeReminderFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::None => 'None',
        };
    }
}
