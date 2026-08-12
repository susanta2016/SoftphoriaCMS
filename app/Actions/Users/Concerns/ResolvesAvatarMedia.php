<?php

namespace App\Actions\Users\Concerns;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Models\User;

/**
 * Shared by CreateUserAction/UpdateUserAction: the avatar form field holds a
 * storage path (from Filament's FileUpload), but user_profiles.avatar_media_id
 * is a foreign key into the existing `media` table (DB-003), so every upload
 * needs a Media row created for it. Comparing against the profile's current
 * avatar path avoids creating a duplicate Media row when the field is
 * re-submitted unchanged.
 *
 * Media row creation itself is delegated to StoreUploadedMediaAction
 * (ADMIN-005) — the same central entry point the Media Library resource
 * uses — so avatar uploads get responsive WebP/AVIF variants for free
 * without duplicating the Media-row-building logic here. This method's own
 * signature/behavior (unchanged-path no-op, blank clears the avatar) is
 * unchanged for CreateUserAction/UpdateUserAction.
 */
trait ResolvesAvatarMedia
{
    /**
     * @return array{avatar_media_id?: int|null}
     */
    private function resolveAvatarMediaId(User $user, ?string $uploadedPath, User $actor): array
    {
        $currentPath = $user->profile?->avatar?->path;

        if ($uploadedPath === $currentPath) {
            return [];
        }

        if (blank($uploadedPath)) {
            return ['avatar_media_id' => null];
        }

        $media = app(StoreUploadedMediaAction::class)->handle('public', $uploadedPath, $actor);

        return ['avatar_media_id' => $media->id];
    }
}
