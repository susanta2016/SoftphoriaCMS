<?php

namespace App\Actions\Users;

use App\Exceptions\Users\CannotModifySelfException;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * The only path by which a user's status may change post-creation
 * (ADMIN-003 §I.1/§I.3 decisions). Guards against an administrator locking
 * themselves out of /admin and records an audit_logs entry per Core
 * Specification Ch.20 ("Role and permission updates" / account changes).
 */
class ChangeUserStatusAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, string $status, User $actor): User
    {
        if ($target->is($actor)) {
            throw CannotModifySelfException::forStatusChange();
        }

        $previousStatus = $target->status;

        $target->status = $status;
        $target->save();

        $this->auditLog->record($actor, 'user.status_changed', $target, [
            'from' => $previousStatus,
            'to' => $status,
        ]);

        return $target;
    }
}
