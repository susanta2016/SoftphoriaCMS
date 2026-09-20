<?php

namespace App\Actions\Auth;

use App\Actions\Auth\Concerns\GeneratesVerificationTokens;
use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public self-registration (AUTH-001) — Guest/Registered only, no
 * membership tier or payment step (per the confirmed spec: "Do NOT
 * implement Paid Premium/Subscription/Membership checkout" at the auth
 * layer). The account is created PendingVerification and never becomes
 * Active here — only VerifyEmailAction flips that, independent of whether
 * this request's emails ever successfully send.
 *
 * PendingVerification is deliberately not a login gate (see
 * EnsureAccountIsUsable) — it only blocks future verified-only actions
 * such as commenting, which is out of scope here and enforced by whichever
 * ticket builds that feature, not by this Action.
 */
class RegisterUserAction
{
    use GeneratesVerificationTokens;

    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->status = UserStatus::PendingVerification->value;
        $user->save();

        $rawToken = $this->issueVerificationToken($user);

        // The user row is already saved above — a broken SMTP config must
        // never turn a successful signup into a 500 for the visitor.
        // verification.resend exists precisely to recover from this.
        $this->notifyUser($user, $rawToken);
        $this->notifyAdmins($user);

        return $user;
    }

    private function notifyUser(User $user, string $rawToken): void
    {
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
            Log::warning('Registration confirmation/verification email failed to send', [
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Same admin-resolution pattern as SubmitContactRequestAction — every
     * active admin-role user, not a separate "admin notification email"
     * setting.
     */
    private function notifyAdmins(User $user): void
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN_SLUG)->first();

        if ($adminRole === null) {
            return;
        }

        $admins = $adminRole->users()->where('status', UserStatus::Active->value)->get();

        foreach ($admins as $admin) {
            try {
                $this->mailer->send('user_registered', EmailRecipientType::Admin, $admin->email, [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Registration admin notification failed to send', [
                    'user_id' => $user->getKey(),
                    'admin_id' => $admin->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
