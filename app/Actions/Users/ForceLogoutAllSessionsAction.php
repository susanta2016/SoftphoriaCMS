<?php

namespace App\Actions\Users;

use App\Exceptions\Users\CannotModifySelfException;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Revokes every active session for a user. Self-protected: revoking your
 * own sessions would sign the acting administrator out mid-action.
 */
class ForceLogoutAllSessionsAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(User $target, User $actor): int
    {
        if ($target->is($actor)) {
            throw CannotModifySelfException::forSessionRevocation();
        }

        $revoked = match (config('session.driver')) {
            'database' => $this->revokeDatabaseSessions($target),
            'redis' => $this->revokeRedisSessions($target),
            default => 0,
        };

        $this->auditLog->record($actor, 'user.sessions_revoked', $target, [
            'revoked' => $revoked,
        ]);

        return $revoked;
    }

    private function revokeDatabaseSessions(User $target): int
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $target->getKey())
            ->delete();
    }

    /**
     * The database session table isn't used when SESSION_DRIVER=redis
     * (Softphoria's configured default), so sessions must be located by
     * scanning the configured Redis connection directly: Laravel's
     * cache-backed session handler stores each session under
     * `<cache prefix><session id>`, PHP-serialized, holding a JSON payload
     * with a `login_{guard}_{sha1(GuardClass)}` key set to the authenticated
     * user's id (Illuminate\Session\SessionManager::createRedisDriver() /
     * Illuminate\Auth\SessionGuard::getName()).
     */
    private function revokeRedisSessions(User $target): int
    {
        $redis = Redis::connection(config('session.connection') ?: 'default');
        $prefix = config('cache.prefix');
        $guard = config('auth.defaults.guard');
        $authKey = 'login_'.$guard.'_'.sha1(SessionGuard::class);

        $revoked = 0;

        foreach ($redis->keys($prefix.'*') as $key) {
            $sessionId = Str::after($key, $prefix);
            $raw = $redis->get($prefix.$sessionId);

            if (! is_string($raw)) {
                continue;
            }

            $payload = @unserialize($raw);
            $data = is_string($payload) ? json_decode($payload, true) : null;

            if (! is_array($data) || (string) ($data[$authKey] ?? null) !== (string) $target->getKey()) {
                continue;
            }

            $redis->del($prefix.$sessionId);
            $revoked++;
        }

        return $revoked;
    }
}
