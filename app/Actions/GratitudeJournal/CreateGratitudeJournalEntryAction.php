<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;

/**
 * Creates a new Gratitude Journal entry for the authenticated member — a
 * light_posts row with source = journal (Gratitude Journal audit §3,
 * distinguishing it from CreatesLightPostOnRegistration's registration
 * posts, which this class never touches). Defaults to public per the
 * client's confirmed default; a member may pass false to create a private
 * entry outright.
 */
class CreateGratitudeJournalEntryAction
{
    public function handle(User $user, string $content, bool $isPublic = true): LightPost
    {
        return $user->lightPosts()->create([
            'source' => LightPostSource::Journal,
            'content' => $content,
            'is_public' => $isPublic,
        ]);
    }
}
