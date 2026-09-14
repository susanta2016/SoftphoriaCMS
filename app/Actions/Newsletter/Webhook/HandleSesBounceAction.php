<?php

namespace App\Actions\Newsletter\Webhook;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles a parsed SES "Bounce" event delivered via
 * App\Http\Controllers\Newsletter\SesWebhookController — only a *permanent*
 * (hard) bounce suppresses the address; a transient/temporary bounce (a full
 * inbox, a greylisting delay, etc.) is expected to resolve itself and must
 * never permanently block future sends. Idempotent by construction: a
 * subscriber already `bounced` is left untouched on a repeat/duplicate SNS
 * delivery of the same event, so no event-id ledger is needed for Phase 1.
 */
class HandleSesBounceAction
{
    /**
     * @param  array<string, mixed>  $sesMessage  the decoded SES notification body
     */
    public function handle(array $sesMessage): void
    {
        $bounce = $sesMessage['bounce'] ?? null;

        if (! is_array($bounce)) {
            Log::warning('Newsletter SES webhook: bounce event missing its "bounce" payload');

            return;
        }

        $bounceType = $bounce['bounceType'] ?? null;

        if ($bounceType !== 'Permanent') {
            Log::info('Newsletter SES webhook: transient/undetermined bounce ignored', [
                'bounce_type' => $bounceType,
            ]);

            return;
        }

        $eventAt = isset($bounce['timestamp']) ? Carbon::parse($bounce['timestamp']) : now();
        $reason = Str::limit((string) ($bounce['bounceSubType'] ?? $bounceType), 255, '');

        foreach ((array) ($bounce['bouncedRecipients'] ?? []) as $recipient) {
            $this->suppress((string) ($recipient['emailAddress'] ?? ''), $eventAt, $reason);
        }
    }

    private function suppress(string $rawEmail, Carbon $eventAt, string $reason): void
    {
        if (trim($rawEmail) === '') {
            return;
        }

        $email = NewsletterSubscriber::normalizeEmail($rawEmail);

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber === null) {
            Log::info('Newsletter SES webhook: hard bounce for an unknown recipient ignored');

            return;
        }

        if ($subscriber->status === 'bounced') {
            return;
        }

        $subscriber->status = 'bounced';
        $subscriber->ses_event_at = $eventAt;
        $subscriber->ses_event_type = 'bounce';
        $subscriber->ses_event_reason = $reason;
        $subscriber->save();

        Log::info('Newsletter subscriber suppressed after a hard bounce', [
            'subscriber_id' => $subscriber->getKey(),
            'reason' => $reason,
        ]);
    }
}
