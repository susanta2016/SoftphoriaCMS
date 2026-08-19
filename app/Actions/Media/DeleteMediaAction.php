<?php

namespace App\Actions\Media;

use App\Actions\Media\Concerns\DeletesMediaVariants;
use App\Actions\Media\Concerns\FindsMediaUsage;
use App\Exceptions\Media\MediaInUseException;
use App\Models\Media;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Storage;

/**
 * The only path by which a Media row may be deleted from the admin Media
 * Library (single-row or bulk) — guards against removing a file still
 * referenced elsewhere (see FindsMediaUsage) and, since nothing previously
 * cleaned up the physical file or its derived variants on delete (they were
 * only ever removed on replace/regenerate), this is also where that cleanup
 * now happens. Media itself still only soft-deletes (no forceDelete()) so
 * the audit trail/upload history is preserved even though the file is gone.
 */
class DeleteMediaAction
{
    use DeletesMediaVariants;
    use FindsMediaUsage;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Media $media, User $actor): void
    {
        $usedBy = $this->findMediaUsage($media);

        if ($usedBy !== []) {
            throw MediaInUseException::forMedia($media, $usedBy);
        }

        $this->deleteExistingVariants($media);

        Storage::disk($media->disk)->delete($media->path);

        $filename = $media->original_filename;

        $media->delete();

        $this->auditLog->record($actor, 'media.deleted', $media, ['filename' => $filename]);
    }
}
