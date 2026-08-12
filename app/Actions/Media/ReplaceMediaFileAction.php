<?php

namespace App\Actions\Media;

use App\Actions\Media\Concerns\DeletesMediaVariants;
use App\Actions\Media\Concerns\EvaluatesImageForVariants;
use App\Enums\MediaCategory;
use App\Exceptions\Media\MediaCategoryMismatchException;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\Media;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces an existing Media record's underlying file in place (ADMIN-005
 * review fix) — the row's id/public_id/uploader_id/created_at and any FKs
 * pointing at it (e.g. user_profiles.avatar_media_id) are preserved; only
 * the file-related columns change. Reuses StoreUploadedMediaAction's
 * variant-eligibility logic via EvaluatesImageForVariants rather than
 * reimplementing it.
 *
 * Ordering is deliberate: Filament's FileUpload has already validated and
 * stored the new file on disk before this runs (identical to
 * StoreUploadedMediaAction's flow), the Media row is updated and the
 * transaction committed before any old asset is touched, and the old
 * original file is only deleted after that commit succeeds — so a failure
 * partway through never leaves the record pointing at nothing, and the old
 * file is never lost before the new one is confirmed in place.
 */
class ReplaceMediaFileAction
{
    use DeletesMediaVariants, EvaluatesImageForVariants;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Media $media, string $disk, string $path, User $actor): Media
    {
        $newMimeType = Storage::disk($disk)->mimeType($path);
        $newCategory = MediaCategory::fromMimeType($newMimeType);
        $existingCategory = $media->category();

        if ($newCategory !== $existingCategory) {
            throw MediaCategoryMismatchException::forReplace($existingCategory, $newCategory);
        }

        $oldDisk = $media->disk;
        $oldPath = $media->path;
        $oldFilename = $media->original_filename;

        DB::transaction(function () use ($media, $disk, $path, $actor, $newMimeType): void {
            $this->deleteExistingVariants($media);

            $media->disk = $disk;
            $media->path = $path;
            $media->original_filename = basename($path);
            $media->mime_type = $newMimeType;
            $media->size = Storage::disk($disk)->size($path);
            $media->updated_by = $actor->getKey();
            // Cleared unconditionally (harmless no-op for non-image
            // categories, which never set them): image processing is
            // pending again until GenerateImageVariantsJob finishes on the
            // new file.
            $media->variants_generated_at = null;
            $media->variants_failed_at = null;
            $media->save();
        });

        // Only after the row update committed and old variants are gone —
        // the record already points at the new file by this point.
        Storage::disk($oldDisk)->delete($oldPath);

        $this->auditLog->record($actor, 'media.replaced', $media, [
            'old_filename' => $oldFilename,
            'new_filename' => $media->original_filename,
            'category' => $newCategory->value,
        ]);

        if ($newCategory === MediaCategory::Image && $this->qualifiesForVariants($media)) {
            GenerateImageVariantsJob::dispatch($media->id);
        }

        return $media;
    }
}
