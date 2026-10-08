<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permanently removes every user whose status is "deleted" — the explicit,
 * confirmed exception to ADMIN-003 §2's "no hard delete" (Admin → Users →
 * "Permanently delete deleted users"). Users with any other status, and the
 * acting administrator, are never touched.
 *
 * Database rules decide what goes with each user: their own rows (profile,
 * preferences, roles, email verifications, downloads, blog comments,
 * comment reports and reactions) are deleted by cascade; content they
 * authored or edited (pages, posts, media, audit entries…) is kept with the
 * user reference set to null. Their login sessions and password-reset tokens
 * are removed here too, since those tables have no foreign key.
 *
 * One audit entry per purged user is recorded first (user id only, no
 * personal data), all in one transaction.
 */
class PurgeDeletedUsersAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /** How many users would be purged. */
    public function count(?User $actor = null): int
    {
        return $this->query($actor)->count();
    }

    /** @return int how many users were permanently deleted */
    public function handle(User $actor): int
    {
        return DB::transaction(function () use ($actor): int {
            $users = $this->query($actor)->lockForUpdate()->get();

            if ($users->isEmpty()) {
                return 0;
            }

            foreach ($users as $user) {
                $this->auditLog->record($actor, 'user.purged', $user, ['previous_status' => $user->status]);
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $users->modelKeys())->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
            }

            $users->each->delete();

            return $users->count();
        });
    }

    private function query(?User $actor)
    {
        return User::query()
            ->where('status', UserStatus::Deleted->value)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()));
    }
}
