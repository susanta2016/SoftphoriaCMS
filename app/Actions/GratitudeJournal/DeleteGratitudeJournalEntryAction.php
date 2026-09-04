<?php

namespace App\Actions\GratitudeJournal;

use App\Models\LightPost;

/**
 * Deletes a member's own Gratitude Journal entry. Ownership is verified by
 * the caller (GratitudeJournalController) before this ever runs.
 */
class DeleteGratitudeJournalEntryAction
{
    public function handle(LightPost $entry): void
    {
        $entry->delete();
    }
}
