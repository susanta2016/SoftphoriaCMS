<?php

namespace App\Actions\Registration;

use App\Actions\Registration\Concerns\GeneratesVerificationTokens;
use App\Actions\Registration\Concerns\SavesOptionalRegistrationProfile;
use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Free registration (point 2 of the confirmed spec). Email uniqueness is
 * already enforced by the caller's validation (`unique:users,email` — any
 * existing row of any status blocks a Free signup outright, unlike Pro's
 * abandoned-registration reuse path, which is deliberately scoped to Pro
 * only). The account is created PendingVerification and never becomes
 * Active here — only VerifyEmailAction flips that, independent of this
 * request ever completing successfully on the mail side.
 */
class RegisterFreeUserAction
{
    use GeneratesVerificationTokens;
    use SavesOptionalRegistrationProfile;

    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array{name: string, email: string, password: string, phone_number?: ?string, bio?: ?string, address?: ?string, zip_code?: ?string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->status = UserStatus::PendingVerification->value;
        $user->save();

        $this->saveOptionalProfile($user, $data);

        $rawToken = $this->issueVerificationToken($user);

        // A broken SMTP config must never turn a successful signup into a
        // 500 for the visitor — the user is already saved above, and
        // resend-verification exists precisely to recover from this.
        try {
            $this->mailer->send('user_registered', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'user_email' => $user->email,
            ]);

            $this->mailer->send('email_verification', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'verification_url' => route('verification.verify', ['token' => $rawToken]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Free registration confirmation/verification email failed to send', [
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return $user;
    }
}
