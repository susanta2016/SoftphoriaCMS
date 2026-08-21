<?php

namespace App\Actions\Media;

use App\Actions\Media\Concerns\EvaluatesImageForVariants;
use App\Actions\Media\Concerns\ReadsFileMetadataReliably;
use App\Enums\MediaCategory;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\Media;
use App\Models\User;

/**
 * Central entry point for turning an already-stored upload (Filament's
 * FileUpload writes directly to the configured disk/directory before this
 * runs) into a Media row. Reused by every uploader — the Media Library
 * resource and ResolvesAvatarMedia (ADMIN-005) — so there is one place that
 * creates Media rows and decides whether an image qualifies for derived
 * variants, instead of each caller reimplementing it. ReplaceMediaFileAction
 * reuses the same variant-eligibility logic via EvaluatesImageForVariants,
 * and the same retrying metadata read via ReadsFileMetadataReliably.
 */
class StoreUploadedMediaAction
{
    use EvaluatesImageForVariants, ReadsFileMetadataReliably;

    public function handle(string $disk, string $path, User $uploader, string $visibility = 'public'): Media
    {
        $metadata = $this->readFileMetadata($disk, $path);

        $media = new Media;
        $media->disk = $disk;
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = $metadata['mimeType'];
        $media->size = $metadata['size'];
        $media->visibility = $visibility;
        $media->uploader_id = $uploader->getKey();
        $media->save();

        if ($media->category() === MediaCategory::Image && $this->qualifiesForVariants($media)) {
            GenerateImageVariantsJob::dispatch($media->id);
        }

        return $media;
    }
}
