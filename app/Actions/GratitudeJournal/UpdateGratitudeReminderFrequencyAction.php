<?php

namespace App\Actions\GratitudeJournal;

use App\Models\User;
use App\Models\UserPreference;
use App\Shared\Support\Users\GratitudeReminderPreference;

/**
 * Stores the member's Gratitude Journal reminder preference (On/Off + how
 * many per week) inside the existing UserPreference.preferences JSON blob
 * (Gratitude Journal audit §7: that existing column is reused, with no new one).
 * Merges into whatever other preference keys already live in that blob
 * (including the scheduler's last-sent/weekly-count bookkeeping) rather
 * than overwriting them, so a change applies from the next scheduled day
 * onward without resetting this week's count.
 */
class UpdateGratitudeReminderFrequencyAction
{
    public function handle(User $user, GratitudeReminderPreference $reminder): void
    {
        // user_id is deliberately excluded from UserPreference's own
        // Fillable list (see that model) — set directly rather than via
        // mass assignment when this is the member's first-ever preference.
        //
        // Always re-read the row rather than trusting an already-loaded
        // relation: the scheduler may have written its send bookkeeping
        // since, and merging into a stale copy would wipe it.
        $preference = $user->preferences()->first() ?? tap(new UserPreference, fn (UserPreference $new) => $new->user_id = $user->getKey());

        $preference->preferences = [
            ...($preference->preferences ?? []),
            ...$reminder->toStored(),
        ];

        $preference->save();

        $user->setRelation('preferences', $preference);
    }
}
