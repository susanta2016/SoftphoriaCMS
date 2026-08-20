<?php

namespace App\Modules\Music\Actions\Album;

use App\Models\User;
use App\Modules\Music\Exceptions\AlbumInUseException;
use App\Modules\Music\Models\Album;
use App\Shared\Services\AuditLogService;

class DeleteAlbumAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Album $album, User $actor): void
    {
        $trackCount = $album->tracks()->count();

        if ($trackCount > 0) {
            throw AlbumInUseException::forAlbum($album, $trackCount);
        }

        $album->delete();

        $this->auditLog->record($actor, 'album.deleted', $album, ['title' => $album->title]);
    }
}
