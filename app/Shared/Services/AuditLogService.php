<?php

namespace App\Shared\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes to the existing audit_logs table (DB-002). Platform-wide concern
 * per Core Specification Ch.20 — not owned by any single module, so it
 * lives here rather than inside a module that doesn't exist yet. No new
 * table or column is introduced; this is the first writer for a table that
 * ADMIN-002's RecentActivityWidget already reads.
 */
class AuditLogService
{
    public function __construct(private readonly Request $request) {}

    /**
     * Sets attributes individually rather than AuditLog::create() —
     * AuditLog (DB-003) declares no #[Fillable] surface, and widening it is
     * outside this stage's scope.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(?User $actor, string $action, Model $entity, array $metadata = []): AuditLog
    {
        $log = new AuditLog;
        $log->user_id = $actor?->getKey();
        $log->action = $action;
        $log->entity_type = class_basename($entity);
        $log->entity_id = $entity->getKey();
        $log->ip_address = $this->request->ip();
        $log->user_agent = $this->request->userAgent();
        $log->metadata = $metadata;
        $log->save();

        return $log;
    }
}
