<?php

namespace App\Actions\Newsletter\Webhook;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles a parsed SES "Complaint" event delivered via
 * App\Http\Controllers\Newsletter\SesWebhookController. Every complaint is a
 * suppression event — there is no "transient complaint" concept the way
 * there is for bounces (see HandleSesBounceAction). Idempotent by
 * construction: a subscriber already `complained` is left untouched on a
 * repeat/duplicate SNS delivery of the same event.
 */
class HandleSesComplaintAction
{
    /**
     * @param  array<string, mixed>  $sesMessage  the decoded SES notification body
     */
    public function handle(array $sesMessage): void
    {
        $complaint = $sesMessage['complaint'] ?? null;

        if (! is_array($complaint)) {
            Log::warning('Newsletter SES webhook: complaint event missing its "complaint" payload');

            return;
        }

        $eventAt = isset($complaint['timestamp']) ? Carbon::parse($complaint['timestamp']) : now();
        $reason = Str::limit((string) ($complaint['complaintFeedbackType'] ?? 'complaint'), 255, '');

        foreach ((array) ($complaint['complainedRecipients'] ?? []) as $recipient) {
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
            Log::info('Newsletter SES webhook: complaint for an unknown recipient ignored');

            return;
        }

        if ($subscriber->status === 'complained') {
            return;
        }

        $subscriber->status = 'complained';
        $subscriber->ses_event_at = $eventAt;
        $subscriber->ses_event_type = 'complaint';
        $subscriber->ses_event_reason = $reason;
        $subscriber->save();

        Log::info('Newsletter subscriber suppressed after a complaint', [
            'subscriber_id' => $subscriber->getKey(),
            'reason' => $reason,
        ]);
    }
}
