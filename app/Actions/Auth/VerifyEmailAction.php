<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `email_verified_at` and the
 * PendingVerification → Active transition. The token row is deleted on
 * success, which is what makes it single-use — a repeat visit to the same
 * link finds no matching row and fails cleanly.
 *
 * Verification happens via an emailed GET link that consumes the token
 * immediately (no separate confirm step). This app has no existing
 * signed-URL/prefetch-safe-link convention to build on (confirmed: no
 * other route on this codebase mutates state from a bare GET token), so
 * this is the accepted Phase 1 trade-off — an automated email/security
 * scanner that follows the link before the real recipient does could
 * consume the token. A two-step "click to confirm" flow would close that
 * gap but is out of scope for AUTH-001→005 unless it becomes a genuine
 * problem in practice.
 */
class VerifyEmailAction
{
    /**
     * @return User|null the now-verified user, or null if the token is
     *                   invalid, already consumed, or expired
     */
    public function handle(string $rawToken): ?User
    {
        $hashed = hash('sha256', $rawToken);

        $verification = EmailVerification::query()->where('token', $hashed)->first();

        if ($verification === null) {
            return null;
        }

        if ($verification->expires_at !== null && $verification->expires_at->isPast()) {
            $verification->delete();

            return null;
        }

        return DB::transaction(function () use ($verification): User {
            /** @var User $user */
            $user = $verification->user;
            $user->email_verified_at = now();
            $user->status = UserStatus::Active->value;
            $user->save();

            $verification->delete();

            return $user;
        });
    }
}
