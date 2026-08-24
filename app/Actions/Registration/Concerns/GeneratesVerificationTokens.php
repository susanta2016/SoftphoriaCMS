<?php

namespace App\Actions\Registration\Concerns;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared by every Action that needs to (re)issue a verification link —
 * registration (Free/Pro) and resend. Only ever the *raw* random token is
 * emailed; the `email_verifications.token` column stores its SHA-256 hash,
 * so a leaked DB row can never be used to verify an account (matches the
 * hashed-at-rest convention Commerce already uses for guest download
 * tokens — see Entitlement). A prior token for the same user is always
 * deleted first, so exactly one token is ever valid per user at a time —
 * issuing a new one implicitly invalidates any older, unconsumed link.
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
