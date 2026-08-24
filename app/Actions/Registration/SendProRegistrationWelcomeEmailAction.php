<?php

namespace App\Actions\Registration;

use App\Actions\Registration\Concerns\GeneratesVerificationTokens;
use App\Enums\EmailRecipientType;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Called from HandleCheckoutSessionCompletedAction, only when the
 * Subscription row was *just* created (`wasRecentlyCreated`) — i.e. exactly
 * once, the first time a given user's Pro payment/subscription is
 * confirmed by Stripe's webhook, never on retries/replays (point 5 of the
 * confirmed spec: the Pro confirmation email must only be triggered after
 * confirmed payment, never before). The verification token is generated
 * here rather than at registration time, since a Pro signup can be
 * abandoned and resumed arbitrarily far apart — creating it eagerly at
 * registration would risk it being stale (past its 24h TTL) by the time
 * payment actually completes.
 */
class SendProRegistrationWelcomeEmailAction
{
    use GeneratesVerificationTokens;

    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(User $user): void
    {
        $rawToken = $this->issueVerificationToken($user);

        try {
            $this->mailer->send('pro_member_registered', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'user_email' => $user->email,
            ]);

            $this->mailer->send('email_verification', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'verification_url' => route('verification.verify', ['token' => $rawToken]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Pro registration welcome/verification email failed to send', [
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
