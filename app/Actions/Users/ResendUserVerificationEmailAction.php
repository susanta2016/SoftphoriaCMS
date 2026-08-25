<?php

namespace App\Actions\Users;

use App\Actions\Registration\ResendVerificationEmailAction;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * The admin-triggered counterpart to the public "didn't get the verification
 * email?" form (removed from the registration page in favor of this row
 * action) — thin wrapper around the same ResendVerificationEmailAction so
 * the eligibility rule (only a PendingVerification account, silent
 * otherwise) and the actual token/mail logic stay in exactly one place.
 * Unlike its public counterpart this one is only ever invoked against a
 * $target already known to be eligible, and records an audit_logs entry
 * like every other admin-triggered User action.
 */
class ResendUserVerificationEmailAction
{
    public function __construct(
        private readonly ResendVerificationEmailAction $resend,
        private readonly AuditLogService $auditLog,
    ) {}

    public function handle(User $target, User $actor): void
    {
        $this->resend->handle($target->email);

        $this->auditLog->record($actor, 'user.verification_email_resent', $target);
    }
}
