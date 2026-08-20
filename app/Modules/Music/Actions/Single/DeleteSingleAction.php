<?php

namespace App\Modules\Music\Actions\Single;

use App\Models\User;
use App\Modules\Music\Exceptions\SingleInUseException;
use App\Modules\Music\Models\Single;
use App\Shared\Services\AuditLogService;

class DeleteSingleAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Single $single, User $actor): void
    {
        if ($single->track()->exists()) {
            throw SingleInUseException::forSingle($single);
        }

        $single->delete();

        $this->auditLog->record($actor, 'single.deleted', $single, ['title' => $single->title]);
    }
}
