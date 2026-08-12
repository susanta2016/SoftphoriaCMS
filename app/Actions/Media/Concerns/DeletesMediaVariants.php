<?php

namespace App\Actions\Media\Concerns;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by ReplaceMediaFileAction and RegenerateMediaVariantsAction
 * (ADMIN-005) — both need to remove a Media record's existing derived
 * variants (files + rows) before a new set is generated. Never touches the
 * original file itself.
 */
trait DeletesMediaVariants
{
    protected function deleteExistingVariants(Media $media): void
    {
        foreach ($media->variants()->get() as $variant) {
            Storage::disk($variant->disk)->delete($variant->path);
            $variant->delete();
        }
    }
}
