<?php

namespace App\Actions\Users;

use App\Exceptions\Users\CannotModifySelfException;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Rotates a user's password to a random, never-disclosed value and emails
 * them a reset link via the existing password broker (same boundary as
 * SendUserPasswordResetLinkAction — no plaintext password is ever set by an
 * administrator). The rotation immediately invalidates the old password,
 * which is why this is self-protected the same way status changes are.
 */
class GenerateNewPasswordAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw CannotModifySelfException::forPasswordGeneration();
        }

        $target->password = Hash::make(Str::random(40));
        $target->save();

        $status = Password::broker()->sendResetLink(['email' => $target->email]);

        $this->auditLog->record($actor, 'user.password_regenerated', $target, [
            'broker_status' => $status,
        ]);
    }
}
