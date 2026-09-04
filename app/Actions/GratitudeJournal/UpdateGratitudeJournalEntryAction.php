<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\GratitudeJournalVisibility;
use App\Models\LightPost;

/**
 * Edits a member's own Gratitude Journal entry's content and/or visibility
 * (Public/Private/For Community). Ownership is verified by the caller
 * (GratitudeJournalController) before this ever runs — this action trusts
 * the $entry it is given.
 */
class UpdateGratitudeJournalEntryAction
{
    public function handle(LightPost $entry, string $content, GratitudeJournalVisibility $visibility): LightPost
    {
        $entry->update([
            'content' => $content,
            'visibility' => $visibility,
        ]);

        return $entry->fresh();
    }
}
