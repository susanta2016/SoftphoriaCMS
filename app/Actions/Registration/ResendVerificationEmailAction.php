<?php

namespace App\Actions\Registration;

use App\Actions\Registration\Concerns\GeneratesVerificationTokens;
use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resend verification (Phase 1, confirmed). Deliberately silent about
 * whether $email belongs to any account: the caller always shows the same
 * generic message regardless of what happens in here, so this method never
 * returns anything the controller could accidentally leak through a
 * different response. Only a PendingVerification account is eligible — an
 * unknown email or an already-Active one is a no-op, not an error.
 */
class ResendVerificationEmailAction
{
    use GeneratesVerificationTokens;

    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(string $email): void
    {
        $user = User::query()
            ->where('email', $email)
            ->where('status', UserStatus::PendingVerification->value)
            ->first();

        if ($user === null) {
            return;
        }

        $rawToken = $this->issueVerificationToken($user);

        try {
            $this->mailer->send('email_verification', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'verification_url' => route('verification.verify', ['token' => $rawToken]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Resend verification email failed to send', [
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
