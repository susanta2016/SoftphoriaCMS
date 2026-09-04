<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;

/**
 * Creates a new Gratitude Journal entry for the authenticated member — a
 * light_posts row with source = journal (Gratitude Journal audit §3,
 * distinguishing it from CreatesLightPostOnRegistration's registration
 * posts, which this class never touches). Defaults to Public per the
 * client's confirmed default; a caller may pass Private or Community to
 * create an entry with either of those visibility states outright.
 */
class CreateGratitudeJournalEntryAction
{
    public function handle(User $user, string $content, GratitudeJournalVisibility $visibility = GratitudeJournalVisibility::Public): LightPost
    {
        return $user->lightPosts()->create([
            'source' => LightPostSource::Journal,
            'content' => $content,
            'visibility' => $visibility,
        ]);
    }
}
