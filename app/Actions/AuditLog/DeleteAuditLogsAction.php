<?php

namespace App\Actions\AuditLog;

use App\Models\AuditLog;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hard-deletes audit log entries chosen in the admin Audit Log table (a
 * single row, a checkbox selection, or "select all" across pages). Works
 * from a query rather than a loaded collection so a mass purge never
 * hydrates thousands of models. The purge itself leaves one fresh
 * "audit_log.deleted" entry behind, so removing history is never silent.
 */
class DeleteAuditLogsAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function handle(Builder $query, User $actor): int
    {
        $ids = (clone $query)->reorder()->pluck('id');

        $deleted = 0;

        foreach ($ids->chunk(500) as $chunk) {
            $deleted += AuditLog::query()->whereKey($chunk->all())->delete();
        }

        if ($deleted > 0) {
            $this->auditLog->record($actor, 'audit_log.deleted', new AuditLog, [
                'count' => $deleted,
                'first_id' => $ids->min(),
                'last_id' => $ids->max(),
            ]);
        }

        return $deleted;
    }
}
