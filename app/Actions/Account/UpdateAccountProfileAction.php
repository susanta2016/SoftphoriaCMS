<?php

namespace App\Actions\Account;

use App\Actions\Registration\Concerns\GeneratesVerificationTokens;
use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The user editing their own name/email/profile fields — never a route
 * parameter, always $user === Auth::user(), so there is no ID to forge to
 * reach another account. Only ever writes name/email (User) and the
 * UserProfile fillable fields; never touches id/status/roles/membership
 * regardless of what the request contains, since $data here is already the
 * validated whitelist the controller built, not the raw request array.
 *
 * Changing the email re-uses the exact registration verification pipeline
 * (GeneratesVerificationTokens + the "email_verification" template) rather
 * than trusting an unconfirmed address — the same reasoning
 * VerifyEmailAction's docblock gives for why verification is independent
 * of everything else: a changed address is unverified until proven
 * otherwise, no matter how "trusted" the session changing it is.
 *
 * @param  array{name: string, email: string, phone_number?: ?string, bio?: ?string, address?: ?string, zip_code?: ?string}  $data
 */
class UpdateAccountProfileAction
{
    use GeneratesVerificationTokens;

    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $emailChanged = $data['email'] !== $user->email;

            $user->name = $data['name'];
            $user->email = $data['email'];

            if ($emailChanged) {
                $user->email_verified_at = null;
                $user->status = UserStatus::PendingVerification->value;
            }

            $user->save();

            $profileData = array_filter([
                'bio' => $data['bio'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'address' => $data['address'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
            ], fn ($value) => filled($value));

            if ($profileData !== []) {
                $user->profile()->updateOrCreate([], $profileData);
            }

            if ($emailChanged) {
                $this->sendVerificationEmail($user);
            }

            return $user;
        });
    }

    private function sendVerificationEmail(User $user): void
    {
        $rawToken = $this->issueVerificationToken($user);

        try {
            $this->mailer->send('email_verification', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'verification_url' => route('verification.verify', ['token' => $rawToken]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Account email-change verification email failed to send', [
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
