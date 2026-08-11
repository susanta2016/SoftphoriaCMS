<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Password;

/**
 * Reuses Laravel's existing password broker (no new auth/password system,
 * per ADMIN-003 §4) so an administrator can get a user back into their
 * account without ever seeing or setting a plaintext password.
 */
class SendUserPasswordResetLinkAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, User $actor): string
    {
        $status = Password::broker()->sendResetLink(['email' => $target->email]);

        $this->auditLog->record($actor, 'user.password_reset_link_sent', $target, [
            'broker_status' => $status,
        ]);

        return $status;
    }
}
