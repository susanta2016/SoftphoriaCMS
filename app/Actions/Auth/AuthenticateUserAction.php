<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The only place `Auth::attempt()` is called from the public site. A
 * credential match alone isn't sufficient — a suspended/locked/banned/
 * deleted user must never end up with an authenticated session, so that
 * check happens here, inside the same action, before this ever returns.
 * Deliberately the same generic message for both a wrong password and a
 * blocked account (matching Laravel's own default "these credentials do
 * not match" wording) — this never tells a caller *why* a login failed,
 * only that it did.
 */
class AuthenticateUserAction
{
    private const array BLOCKED_STATUSES = [
        UserStatus::Suspended->value,
        UserStatus::Locked->value,
        UserStatus::Banned->value,
        UserStatus::Deleted->value,
    ];

    /**
     * @throws ValidationException
     */
    public function handle(string $email, string $password, bool $remember): User
    {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (in_array($user->status, self::BLOCKED_STATUSES, true)) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Admin accounts sign in only through /admin/login (Filament's own
        // panel guard check, User::canAccessPanel()) — never through the
        // public member-facing form. Credentials already matched at this
        // point, so naming the reason isn't an information leak the way it
        // would be above.
        if ($user->hasAdminRole()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Admin accounts must sign in at /admin/login.',
            ]);
        }

        return $user;
    }
}
