<?php

namespace App\Actions\Users\Concerns;

use App\Models\User;

/**
 * Shared by CreateUserAction/UpdateUserAction. The avatar form field is now
 * built with the shared MediaPicker (ADMIN-006 convention, docs/ARCHITECTURE.md
 * §14) rather than a bare FileUpload, so it already dehydrates a `media.id`
 * (or null) — MediaPicker's own "Upload New Media" action has already
 * created the Media row via StoreUploadedMediaAction by the time the form is
 * submitted. This method's only remaining job is comparing against the
 * profile's current avatar to avoid an unnecessary profile write when the
 * field is resubmitted unchanged.
 */
trait ResolvesAvatarMedia
{
    /**
     * @return array{avatar_media_id?: int|null}
     */
    private function resolveAvatarMediaId(User $user, int|string|null $mediaId): array
    {
        $mediaId = is_numeric($mediaId) ? (int) $mediaId : null;
        $currentMediaId = $user->profile?->avatar_media_id;

        if ($mediaId === $currentMediaId) {
            return [];
        }

        return ['avatar_media_id' => $mediaId];
    }
}
