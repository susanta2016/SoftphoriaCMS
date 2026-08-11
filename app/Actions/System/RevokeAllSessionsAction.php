<?php

namespace App\Actions\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Platform-wide "Clear All Sessions" system tool
 * (docs/Reference UI/Admin/Admin navigation UI.docx). Unlike
 * App\Actions\Users\ForceLogoutAllSessionsAction (which targets one user's
 * sessions), this revokes every session except the one making the request,
 * so the acting admin isn't immediately signed out by their own click.
 */
class RevokeAllSessionsAction
{
    public function handle(?string $exceptSessionId = null): int
    {
        return match (config('session.driver')) {
            'database' => $this->revokeDatabaseSessions($exceptSessionId),
            'redis' => $this->revokeRedisSessions($exceptSessionId),
            default => 0,
        };
    }

    private function revokeDatabaseSessions(?string $exceptSessionId): int
    {
        return DB::table(config('session.table', 'sessions'))
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    /**
     * See App\Actions\Users\ForceLogoutAllSessionsAction for how this key
     * format was derived empirically against Softphoria's Redis-backed
     * session store.
     */
    private function revokeRedisSessions(?string $exceptSessionId): int
    {
        $redis = Redis::connection(config('session.connection') ?: 'default');
        $prefix = config('cache.prefix');

        $revoked = 0;

        foreach ($redis->keys($prefix.'*') as $key) {
            $sessionId = Str::after($key, $prefix);

            if ($sessionId === $exceptSessionId) {
                continue;
            }

            $redis->del($prefix.$sessionId);
            $revoked++;
        }

        return $revoked;
    }
}
