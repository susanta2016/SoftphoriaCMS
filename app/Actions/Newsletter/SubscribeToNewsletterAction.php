<?php

namespace App\Actions\Newsletter;

use App\Enums\EmailRecipientType;
use App\Models\NewsletterSubscriber;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public newsletter signup's only entry point (docs/ARCHITECTURE.md
 * §16.5/§16.7 — every module sends email through TemplatedMailer, never a
 * bespoke Mailable). Immediate-confirmation flow: the subscriber row is
 * saved as subscribed right away and the "newsletter_subscribed" template
 * is sent — no double opt-in / confirm-link step. Re-submitting an email
 * that unsubscribed earlier resubscribes it, matching the plain "sign up
 * for updates" expectation of a footer form rather than a support ticket.
 */
class SubscribeToNewsletterAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(string $email, ?string $name = null): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $subscriber->name = $name ?: $subscriber->name;
        $subscriber->status = 'subscribed';
        $subscriber->consented_at = now();
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        // A broken SMTP config must never turn a successful signup into a
        // 500 for the visitor — the subscriber is already saved above.
        try {
            $this->mailer->send('newsletter_subscribed', EmailRecipientType::User, $email, [
                'subscriber_email' => $email,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Newsletter confirmation email failed to send', [
                'email' => $email,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $subscriber;
    }
}
