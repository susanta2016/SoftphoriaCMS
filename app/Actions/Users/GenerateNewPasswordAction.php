<?php

namespace App\Actions\Users;

use App\Exceptions\Users\CannotModifySelfException;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rotates a user's password to a random, never-disclosed value and emails
 * them a reset link via the existing password broker (same boundary as
 * SendUserPasswordResetLinkAction — no plaintext password is ever set by an
 * administrator). The rotation immediately invalidates the old password,
 * which is why this is self-protected the same way status changes are.
 *
 * The password is already rotated by the time the reset-link email is sent,
 * so a mail-transport failure here can't be allowed to bubble up (crashing
 * the admin's Filament action) — it would leave the target locked out with
 * no reset link and no visible error. Caught and logged like every other
 * mail send-site in this codebase, and reported back to the caller via the
 * returned broker status so the admin UI can tell success from failure.
 */
class GenerateNewPasswordAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, User $actor): string
    {
        if ($target->is($actor)) {
            throw CannotModifySelfException::forPasswordGeneration();
        }

        $target->password = Hash::make(Str::random(40));
        $target->save();

        try {
            $status = Password::broker()->sendResetLink(['email' => $target->email]);
        } catch (Throwable $exception) {
            Log::warning('Admin-triggered password regeneration: reset link failed to send', [
                'user_id' => $target->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $status = 'passwords.send_failed';
        }

        $this->auditLog->record($actor, 'user.password_regenerated', $target, [
            'broker_status' => $status,
        ]);

        return $status;
    }
}
