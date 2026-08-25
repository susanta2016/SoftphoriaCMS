<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The user changing their own password from the account area (distinct
 * from GenerateNewPasswordAction/SendUserPasswordResetLinkAction, which are
 * admin-triggered on someone *else's* account and go through the
 * broker/email — this is an immediate, self-service change once the
 * current password is already proven). Laravel's own `Hash` facade does
 * both the verification and the new hashing; nothing here ever logs or
 * stores a plaintext password.
 *
 * `Auth::guard('web')->logoutOtherDevices()` was deliberately NOT used here:
 * it only rehashes the password column for the `AuthenticateSession`
 * middleware to detect on a *subsequent request* — this app never registers
 * that middleware, so it would silently do nothing. Instead this reuses
 * ForceLogoutAllSessionsAction's own driver-aware session revocation
 * (database vs Redis, per config('session.driver')), excluding the
 * caller's own current session id so this device stays logged in.
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

        match (config('session.driver')) {
            'database' => $this->revokeOtherDatabaseSessions($user, $currentSessionId),
            'redis' => $this->revokeOtherRedisSessions($user, $currentSessionId),
            default => null,
        };
    }

    private function revokeOtherDatabaseSessions(User $user, string $currentSessionId): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * Same key-shape reasoning as ForceLogoutAllSessionsAction's own
     * revokeRedisSessions() docblock — mirrored here rather than shared,
     * since the one difference (excluding the caller's own session id) made
     * a shared helper not worth the indirection for two call sites.
     */
    private function revokeOtherRedisSessions(User $user, string $currentSessionId): void
    {
        $redis = Redis::connection(config('session.connection') ?: 'default');
        $prefix = config('cache.prefix');
        $guard = config('auth.defaults.guard');
        $authKey = 'login_'.$guard.'_'.sha1(SessionGuard::class);

        foreach ($redis->keys($prefix.'*') as $key) {
            $sessionId = Str::after($key, $prefix);

            if ($sessionId === $currentSessionId) {
                continue;
            }

            $raw = $redis->get($prefix.$sessionId);

            if (! is_string($raw)) {
                continue;
            }

            $payload = @unserialize($raw);
            $data = is_string($payload) ? json_decode($payload, true) : null;

            if (! is_array($data) || (string) ($data[$authKey] ?? null) !== (string) $user->getKey()) {
                continue;
            }

            $redis->del($prefix.$sessionId);
        }
    }
}
