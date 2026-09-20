<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The user changing their own password from the account area (distinct
 * from GenerateNewPasswordAction/SendUserPasswordResetLinkAction, which are
 * admin-triggered on someone *else's* account and go through the
 * broker/email — this is an immediate, self-service change once the
 * current password is already proven).
 *
 * `Auth::guard('web')->logoutOtherDevices()` is deliberately NOT used
 * here: it only rehashes the password column for Laravel's
 * AuthenticateSession middleware to detect on a *subsequent request*, and
 * this app never registers that middleware on the public 'web' group.
 * Instead this revokes every other row in the `sessions` table for this
 * user directly, excluding the caller's own current session id so this
 * device stays logged in. Scoped to `session.driver === 'database'` only
 * (this app's configured default, see config/session.php) — a future
 * switch to a non-database session driver would need this action revisited.
 */
class ChangeAccountPasswordAction
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $currentPassword, string $newPassword, string $currentSessionId): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }
    }
}
