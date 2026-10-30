<?php

namespace App\Modules\Newsletter\Support;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Models\NewsletterSuppression;
use Illuminate\Support\Str;

/**
 * The only writer of newsletter_suppressions. Suppressing an address also
 * moves its subscriber row (if any) to the matching status, so the two
 * never disagree; scopeSendable() checks both anyway.
 *
 * A stronger reason always replaces a weaker one (an unsubscribed address
 * that later hard-bounces becomes a hard bounce), never the reverse: an
 * unsubscribe never downgrades a bounce/complaint into something double
 * opt-in could lift.
 */
class SuppressionList
{
    public function find(string $email): ?NewsletterSuppression
    {
        return NewsletterSuppression::query()->where('email', NewsletterSubscriber::normalizeEmail($email))->first();
    }

    public function isSuppressed(string $email): bool
    {
        return $this->find($email) !== null;
    }

    /**
     * Suppressed for a reason double opt-in can't lift — i.e. anything but
     * a plain unsubscribe. The public signup refuses these.
     */
    public function blocksResubscribe(string $email): bool
    {
        $suppression = $this->find($email);

        return $suppression !== null && $suppression->reason !== SuppressionReason::Unsubscribed;
    }

    public function suppress(string $email, SuppressionReason $reason, ?string $detail = null, ?User $by = null): NewsletterSuppression
    {
        $email = NewsletterSubscriber::normalizeEmail($email);
        $existing = NewsletterSuppression::query()->where('email', $email)->first();

        if ($existing !== null && $existing->reason !== SuppressionReason::Unsubscribed && $reason === SuppressionReason::Unsubscribed) {
            return $existing;
        }

        $suppression = NewsletterSuppression::query()->updateOrCreate(['email' => $email], [
            'reason' => $reason,
            'detail' => $detail !== null ? Str::limit($detail, 250, '') : null,
            'suppressed_by_user_id' => $by?->getKey(),
        ]);

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber !== null) {
            $subscriber->status = $reason->subscriberStatus()->value;

            if ($reason === SuppressionReason::Unsubscribed && $subscriber->unsubscribed_at === null) {
                $subscriber->unsubscribed_at = now();
            }

            $subscriber->save();
        }

        return $suppression;
    }

    /**
     * Removes the suppression only. The subscriber is NOT made Active —
     * they become Unsubscribed and need fresh consent (admin attestation or
     * double opt-in) to be sendable again.
     */
    public function lift(string $email): void
    {
        $email = NewsletterSubscriber::normalizeEmail($email);

        NewsletterSuppression::query()->where('email', $email)->delete();

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber !== null && $subscriber->status !== SubscriberStatus::Subscribed->value && $subscriber->status !== SubscriberStatus::Pending->value) {
            $subscriber->status = SubscriberStatus::Unsubscribed->value;
            $subscriber->save();
        }
    }

    /**
     * Called when the subscriber re-confirms through double opt-in: lifts a
     * plain unsubscribe, leaves every other reason in place.
     */
    public function liftUnsubscribe(string $email): void
    {
        NewsletterSuppression::query()
            ->where('email', NewsletterSubscriber::normalizeEmail($email))
            ->where('reason', SuppressionReason::Unsubscribed->value)
            ->delete();
    }
}
