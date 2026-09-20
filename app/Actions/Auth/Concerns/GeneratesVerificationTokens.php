<?php

namespace App\Actions\Auth\Concerns;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared by every Action that needs to (re)issue an email verification
 * link — registration, resend, and an account email change. Writes to the
 * existing `email_verifications` table (DB-002, predates AUTH-001/003).
 * Only the raw token is ever emailed; the stored `token` column is its
 * SHA-256 hash, so a leaked DB row can never be used to verify an account.
 * A prior token for the same user is always deleted first, so exactly one
 * token is ever valid per user at a time.
 */
trait GeneratesVerificationTokens
{
    /**
     * @return string the raw (unhashed) token to embed in the verification URL
     */
    protected function issueVerificationToken(User $user): string
    {
        EmailVerification::query()->where('user_id', $user->getKey())->delete();

        $rawToken = Str::random(64);

        $verification = new EmailVerification;
        $verification->user_id = $user->getKey();
        $verification->email = $user->email;
        $verification->token = hash('sha256', $rawToken);
        $verification->expires_at = now()->addHours(24);
        $verification->save();

        return $rawToken;
    }
}
