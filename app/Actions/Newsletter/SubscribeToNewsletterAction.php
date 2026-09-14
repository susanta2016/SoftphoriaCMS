<?php

namespace App\Actions\Newsletter;

use App\Enums\EmailRecipientType;
use App\Enums\NewsletterSubscriptionOutcome;
use App\Models\NewsletterSubscriber;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The public newsletter signup's only entry point (docs/ARCHITECTURE.md
 * §16.5/§16.7 — every module sends email through TemplatedMailer, never a
 * bespoke Mailable). Double opt-in: a new or re-subscribing signup is saved
 * as `pending` and a "newsletter_confirmation" email carrying a single-use
 * link is sent; the row only becomes `subscribed` once
 * ConfirmNewsletterSubscriptionAction verifies that link's token. An address
 * already `bounced`/`complained` by SES (see App\Actions\Newsletter\Webhook)
 * cannot resubscribe through this form — that suppression is only ever
 * lifted by an explicit admin edit in the Filament resource.
 */
class SubscribeToNewsletterAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(string $email, ?string $name = null): NewsletterSubscriptionOutcome
    {
        $email = NewsletterSubscriber::normalizeEmail($email);

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber !== null && in_array($subscriber->status, NewsletterSubscriber::SUPPRESSED_STATUSES, true)) {
            return NewsletterSubscriptionOutcome::Suppressed;
        }

        if ($subscriber !== null && $subscriber->status === 'subscribed') {
            // Already subscribed — preserve that state, don't re-issue a
            // confirmation email for something already confirmed.
            if (filled($name)) {
                $subscriber->name = $name;
                $subscriber->save();
            }

            return NewsletterSubscriptionOutcome::AlreadySubscribed;
        }

        // New subscriber, a still-pending one re-requesting their link, or a
        // previously unsubscribed address signing up again — all three go
        // through the same pending + confirmation-email path so an
        // unsubscribe can never be silently undone by a resubmission.
        $subscriber ??= new NewsletterSubscriber(['email' => $email]);
        $subscriber->name = $name ?: $subscriber->name;
        $subscriber->status = 'pending';
        $subscriber->consented_at = null;
        $subscriber->confirmed_at = null;

        $rawToken = Str::random(64);
        $subscriber->confirmation_token_hash = hash('sha256', $rawToken);
        $subscriber->confirmation_expires_at = now()->addHours(24);
        $subscriber->save();

        // A broken SMTP config must never turn a successful signup into a
        // 500 for the visitor — the subscriber is already saved above.
        try {
            $this->mailer->send('newsletter_confirmation', EmailRecipientType::User, $email, [
                'confirmation_url' => route('newsletter.confirm', ['token' => $rawToken]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Newsletter confirmation email failed to send', [
                'subscriber_id' => $subscriber->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return NewsletterSubscriptionOutcome::ConfirmationSent;
    }
}
