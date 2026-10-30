<?php

namespace App\Modules\Newsletter\Actions\Subscribers;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Support\SuppressionList;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Every admin change to whether a subscriber may be emailed. Consent and
 * suppression are only ever changed here (or by the subscriber themself),
 * never by editing the record or its groups:
 *
 * - create(): a new Active subscriber needs the admin to attest consent.
 * - unsubscribe(): on the subscriber's behalf (e.g. they emailed in).
 * - resubscribe(): needs fresh, recorded consent, and is refused while the
 *   address has a bounce/complaint/invalid/admin suppression — that must be
 *   lifted explicitly first (liftSuppression()), so it can't happen by
 *   accident.
 * - suppress() / liftSuppression(): admin suppression.
 *
 * Everything is audited.
 */
class ManageSubscriptionAction
{
    use EnsuresNewsletterAdmin;

    public function __construct(
        private readonly SuppressionList $suppressions,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<int, int|string>  $groupIds
     * @param  array<string, string>|null  $profileFields
     */
    public function create(User $admin, string $email, ?string $firstName, ?string $lastName, array $groupIds, bool $consentConfirmed, string $source = 'admin', ?array $profileFields = null): NewsletterSubscriber
    {
        $this->ensureAdmin($admin, requiresModule: false);

        $email = NewsletterSubscriber::normalizeEmail($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw NewsletterException::invalid('Enter a valid email address.');
        }

        if (! $consentConfirmed) {
            throw NewsletterException::invalid('Confirm that this person agreed to receive the newsletter.');
        }

        if (NewsletterSubscriber::query()->where('email', $email)->exists()) {
            throw NewsletterException::invalid('A subscriber with this email address already exists — edit that one instead.');
        }

        if ($this->suppressions->blocksResubscribe($email)) {
            throw NewsletterException::invalid('This address is on the suppression list. Lift the suppression from its subscriber record first.');
        }

        return DB::transaction(function () use ($admin, $email, $firstName, $lastName, $groupIds, $source, $profileFields): NewsletterSubscriber {
            $subscriber = NewsletterSubscriber::query()->create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim("{$firstName} {$lastName}") ?: null,
                'status' => SubscriberStatus::Subscribed->value,
                'source' => $source,
                'profile_fields' => $profileFields ?: null,
                'consented_at' => now(),
            ]);

            $subscriber->groups()->sync(array_map('intval', $groupIds));
            $this->suppressions->liftUnsubscribe($email);

            $this->auditLog->record($admin, 'newsletter_subscriber.created', $subscriber, ['source' => $source, 'consent' => 'attested_by_admin']);

            return $subscriber;
        });
    }

    public function unsubscribe(NewsletterSubscriber $subscriber, User $admin): void
    {
        $this->ensureAdmin($admin, requiresModule: false);

        $this->suppressions->suppress($subscriber->email, SuppressionReason::Unsubscribed, 'Unsubscribed by an admin', $admin);
        $this->auditLog->record($admin, 'newsletter_subscriber.unsubscribed', $subscriber);
    }

    public function resubscribe(NewsletterSubscriber $subscriber, User $admin, string $consentNote): void
    {
        $this->ensureAdmin($admin, requiresModule: false);

        if (trim($consentNote) === '') {
            throw NewsletterException::invalid('Record how this person gave consent again.');
        }

        if ($this->suppressions->blocksResubscribe($subscriber->email)) {
            throw NewsletterException::invalid('This address is suppressed ('.$this->suppressions->find($subscriber->email)?->reason->getLabel().'). Lift the suppression first.');
        }

        DB::transaction(function () use ($subscriber, $admin, $consentNote): void {
            $this->suppressions->liftUnsubscribe($subscriber->email);

            $subscriber->forceFill([
                'status' => SubscriberStatus::Subscribed->value,
                'consented_at' => now(),
                'unsubscribed_at' => null,
            ])->save();

            $this->auditLog->record($admin, 'newsletter_subscriber.resubscribed', $subscriber, ['consent_note' => mb_substr($consentNote, 0, 500)]);
        });
    }

    public function suppress(NewsletterSubscriber $subscriber, User $admin, ?string $note = null): void
    {
        $this->ensureAdmin($admin, requiresModule: false);

        $this->suppressions->suppress($subscriber->email, SuppressionReason::Admin, $note, $admin);
        $this->auditLog->record($admin, 'newsletter_subscriber.suppressed', $subscriber, ['note' => $note]);
    }

    /**
     * Leaves the subscriber Unsubscribed: lifting a suppression is not consent.
     */
    public function liftSuppression(NewsletterSubscriber $subscriber, User $admin, string $reason): void
    {
        $this->ensureAdmin($admin, requiresModule: false);

        if (trim($reason) === '') {
            throw NewsletterException::invalid('Say why the suppression is being lifted.');
        }

        $previous = $this->suppressions->find($subscriber->email);

        $this->suppressions->lift($subscriber->email);
        $this->auditLog->record($admin, 'newsletter_subscriber.suppression_lifted', $subscriber, [
            'previous_reason' => $previous?->reason->value,
            'reason' => mb_substr($reason, 0, 500),
        ]);
    }
}
