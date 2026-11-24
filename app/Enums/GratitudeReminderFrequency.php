<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The original Daily/Weekly/None Gratitude Journal reminder cadence, stored
 * as `gratitude_reminder_frequency` in the UserPreference.preferences JSON
 * blob. Superseded on 2026-11-24 by On/Off + 1–7 reminders per week
 * (App\Shared\Support\Users\GratitudeReminderPreference). Still used to
 * read members' existing values, and still written as a rollback-safe
 * mirror. It is no longer what the member chooses on the page.
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
