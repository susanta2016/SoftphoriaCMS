<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\GratitudeReminderFrequency;
use App\Models\User;
use App\Models\UserPreference;

/**
 * Stores the member's Gratitude Journal reminder cadence inside the
 * existing UserPreference.preferences JSON blob (Gratitude Journal audit
 * §7 — reusing that already-existing, previously-unused column rather than
 * adding a new one). Merges into whatever other preference keys already
 * live in that blob rather than overwriting them.
 */
class UpdateGratitudeReminderFrequencyAction
{
    public function handle(User $user, GratitudeReminderFrequency $frequency): void
    {
        // user_id is deliberately excluded from UserPreference's own
        // Fillable list (see that model) — set directly rather than via
        // mass assignment when this is the member's first-ever preference.
        $preference = $user->preferences ?? tap(new UserPreference, fn (UserPreference $new) => $new->user_id = $user->getKey());

        $preference->preferences = [
            ...($preference->preferences ?? []),
            'gratitude_reminder_frequency' => $frequency->value,
        ];

        $preference->save();
    }
}
