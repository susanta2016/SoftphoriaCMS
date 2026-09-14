<?php

namespace App\Actions\Newsletter;

use App\Enums\EmailRecipientType;
use App\Models\NewsletterSubscriber;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only writer of the `pending` -> `subscribed` transition (mirrors
 * App\Actions\Registration\VerifyEmailAction's own token pattern). The token
 * hash is cleared on success, which is what makes it single-use — a second
 * visit to the same link finds no row with that hash anymore and fails the
 * same generic way an invalid token does, per the controller's own
 * docblock. The `newsletter_subscribed` notification (the pre-existing
 * template, now meaning "you're confirmed" rather than "you signed up") is
 * only ever sent from here, never from SubscribeToNewsletterAction.
 */
class ConfirmNewsletterSubscriptionAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @return NewsletterSubscriber|null the now-subscribed row, or null if
     *                                   the token is invalid, already
     *                                   consumed, or expired
     */
    public function handle(string $rawToken): ?NewsletterSubscriber
    {
        $hashed = hash('sha256', $rawToken);

        $subscriber = NewsletterSubscriber::query()
            ->where('confirmation_token_hash', $hashed)
            ->where('status', 'pending')
            ->first();

        if ($subscriber === null) {
            return null;
        }

        if ($subscriber->confirmation_expires_at !== null && $subscriber->confirmation_expires_at->isPast()) {
            $subscriber->confirmation_token_hash = null;
            $subscriber->confirmation_expires_at = null;
            $subscriber->save();

            return null;
        }

        $subscriber->status = 'subscribed';
        $subscriber->confirmed_at = now();
        $subscriber->consented_at = now();
        $subscriber->unsubscribed_at = null;
        $subscriber->confirmation_token_hash = null;
        $subscriber->confirmation_expires_at = null;
        $subscriber->save();

        try {
            $this->mailer->send('newsletter_subscribed', EmailRecipientType::User, $subscriber->email, [
                'subscriber_email' => $subscriber->email,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Newsletter subscribed notification failed to send', [
                'subscriber_id' => $subscriber->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return $subscriber;
    }
}
