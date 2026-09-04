<?php

namespace App\Actions\GratitudeJournal;

use App\Models\LightPost;

/**
 * Edits a member's own Gratitude Journal entry's content and/or Public/
 * Private visibility. Ownership is verified by the caller
 * (GratitudeJournalController) before this ever runs — this action trusts
 * the $entry it is given.
 */
class UpdateGratitudeJournalEntryAction
{
    public function handle(LightPost $entry, string $content, bool $isPublic): LightPost
    {
        $entry->update([
            'content' => $content,
            'is_public' => $isPublic,
        ]);

        return $entry->fresh();
    }
}
