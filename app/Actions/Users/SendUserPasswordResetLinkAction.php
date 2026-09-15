<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Reuses Laravel's existing password broker (no new auth/password system,
 * per ADMIN-003 §4) so an administrator can get a user back into their
 * account without ever seeing or setting a plaintext password.
 *
 * Unlike Password::broker()->sendResetLink()'s normal callers, this one runs
 * inside a Filament action — an uncaught mail-transport exception here
 * doesn't just fail the email, it crashes the whole Livewire request (the
 * generic "There was an error while attempting to load this page" screen).
 * So, like every other mail send-site in this codebase (e.g.
 * ResendVerificationEmailAction, SendOrderConfirmationEmailAction), the send
 * is wrapped and logged rather than left to bubble up.
 */
class SendUserPasswordResetLinkAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, User $actor): string
    {
        try {
            $status = Password::broker()->sendResetLink(['email' => $target->email]);
        } catch (Throwable $exception) {
            Log::warning('Admin-triggered password reset link failed to send', [
                'user_id' => $target->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $status = 'passwords.send_failed';
        }

        $this->auditLog->record($actor, 'user.password_reset_link_sent', $target, [
            'broker_status' => $status,
        ]);

        return $status;
    }
}
