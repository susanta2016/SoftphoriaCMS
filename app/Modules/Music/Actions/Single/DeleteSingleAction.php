<?php

namespace App\Modules\Music\Actions\Single;

use App\Models\User;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Music\Exceptions\SingleInUseException;
use App\Modules\Music\Models\Single;
use App\Shared\Services\AuditLogService;

/**
 * ADMIN-008 note: see DeleteAlbumAction's docblock for why the purchased
 * guard below deliberately reaches into Commerce's OrderItem directly.
 */
class DeleteSingleAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Single $single, User $actor): void
    {
        if ($single->track()->exists()) {
            throw SingleInUseException::forSingle($single);
        }

        if (OrderItem::query()->where('single_id', $single->getKey())->exists()) {
            throw SingleInUseException::forPurchasedSingle($single);
        }

        $single->delete();

        $this->auditLog->record($actor, 'single.deleted', $single, ['title' => $single->title]);
    }
}
