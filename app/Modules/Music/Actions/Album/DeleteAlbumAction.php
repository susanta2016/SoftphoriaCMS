<?php

namespace App\Modules\Music\Actions\Album;

use App\Models\User;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Music\Exceptions\AlbumInUseException;
use App\Modules\Music\Models\Album;
use App\Shared\Services\AuditLogService;

/**
 * ADMIN-008 note: the purchased-Album guard below is a deliberate, narrow
 * exception to the "a module never reaches into another module's internals"
 * boundary (docs/ARCHITECTURE.md §7) — Commerce's OrderItem is queried
 * directly rather than through an event/contract, because "you must not be
 * able to delete something a customer paid for" is a hard safety
 * requirement, not a layering nicety. Flagged here rather than done
 * silently; redirect to an event-based check instead if that boundary
 * matters more than this does.
 */
class DeleteAlbumAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Album $album, User $actor): void
    {
        $trackCount = $album->tracks()->count();

        if ($trackCount > 0) {
            throw AlbumInUseException::forAlbum($album, $trackCount);
        }

        if (OrderItem::query()->where('album_id', $album->getKey())->exists()) {
            throw AlbumInUseException::forPurchasedAlbum($album);
        }

        $album->delete();

        $this->auditLog->record($actor, 'album.deleted', $album, ['title' => $album->title]);
    }
}
