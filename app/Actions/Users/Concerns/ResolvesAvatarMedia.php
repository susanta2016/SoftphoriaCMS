<?php

namespace App\Actions\Users\Concerns;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by CreateUserAction/UpdateUserAction: the avatar form field holds a
 * storage path (from Filament's FileUpload), but user_profiles.avatar_media_id
 * is a foreign key into the existing `media` table (DB-003), so every upload
 * needs a Media row created for it. Comparing against the profile's current
 * avatar path avoids creating a duplicate Media row when the field is
 * re-submitted unchanged.
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

        $media = new Media;
        $media->disk = 'public';
        $media->path = $uploadedPath;
        $media->original_filename = basename($uploadedPath);
        $media->mime_type = Storage::disk('public')->mimeType($uploadedPath);
        $media->size = Storage::disk('public')->size($uploadedPath);
        $media->visibility = 'public';
        $media->uploader_id = $actor->getKey();
        $media->save();

        return ['avatar_media_id' => $media->id];
    }
}
