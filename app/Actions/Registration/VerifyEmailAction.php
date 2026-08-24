<?php

namespace App\Actions\Registration;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `email_verified_at` and the
 * PendingVerification → Active transition (point 6 of the confirmed spec:
 * this is fully independent of Subscription state — a paid Pro user can sit
 * here with an already-Active subscription while still PendingVerification).
 * The token row is deleted on success, which is what makes it single-use —
 * a repeat visit to the same link finds no matching row and fails cleanly.
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
