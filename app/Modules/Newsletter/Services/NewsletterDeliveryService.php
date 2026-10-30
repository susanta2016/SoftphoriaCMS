<?php

namespace App\Modules\Newsletter\Services;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Mail\NewsletterMailer;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterDeliveryLog;
use App\Modules\Newsletter\Support\NewsletterFeature;
use App\Modules\Newsletter\Support\NewsletterRenderer;
use App\Modules\Newsletter\Support\RenderedEmail;
use App\Modules\Newsletter\Support\SuppressionList;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one campaign email to one recipient — the only code that does.
 *
 * Order matters here:
 *  1. Refuse when the module is off (NewsletterFeature), whoever calls.
 *  2. Claim the recipient with an atomic pending -> sending update; only
 *     one worker can win it, so overlapping jobs, retries or a duplicate
 *     chain can't send twice.
 *  3. Re-check consent right before sending: still Active, not on the
 *     suppression list, same address as when snapshotted. A recipient who
 *     unsubscribed after the campaign was scheduled is Skipped.
 *  4. Send, then record the outcome. A provider error leaves the
 *     recipient Pending for another attempt (up to
 *     config('newsletter.max_attempts')), then Failed.
 *
 * A worker dying between 4's send and its bookkeeping leaves the recipient
 * at Sending; DispatchDueCampaignsCommand later marks those Failed rather
 * than retrying, because whether the email went out is unknown and a
 * duplicate is worse than a gap.
 */
class NewsletterDeliveryService
{
    public function __construct(
        private readonly NewsletterRenderer $renderer,
        private readonly NewsletterMailer $mailer,
        private readonly SuppressionList $suppressions,
    ) {}

    public function deliver(NewsletterCampaign $campaign, NewsletterCampaignRecipient $recipient, RenderedEmail $snapshot): CampaignRecipientStatus
    {
        NewsletterFeature::ensureEnabled();

        $claimed = NewsletterCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->update([
                'status' => CampaignRecipientStatus::Sending->value,
                'attempts' => $recipient->attempts + 1,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return $recipient->fresh()?->status ?? CampaignRecipientStatus::Skipped;
        }

        $recipient->refresh();

        // toBase(): the raw column, not the enum cast.
        if (NewsletterCampaign::query()->whereKey($campaign->getKey())->toBase()->value('status') !== CampaignStatus::Sending->value) {
            return $this->finish($recipient, CampaignRecipientStatus::Cancelled, 'Campaign is no longer sending.');
        }

        if (($skipReason = $this->ineligibilityReason($recipient)) !== null) {
            return $this->finish($recipient, CampaignRecipientStatus::Skipped, $skipReason);
        }

        $email = $this->renderer->personalize($snapshot, $recipient);
        $messageId = $recipient->token.'@'.Str::after($campaign->from_email, '@');
        $mailerName = $this->mailer->mailerName();

        try {
            $providerMessageId = $this->mailer->send($recipient->email, new NewsletterCampaignMail(
                subjectLine: $this->renderer->subject($campaign, $recipient->only(['first_name', 'last_name', 'email'])),
                htmlBody: $email->html,
                textBody: $email->text,
                fromAddress: $campaign->from_email,
                fromName: $campaign->from_name,
                oneClickUnsubscribeUrl: route('newsletter.unsubscribe', ['token' => $recipient->token]),
                messageIdentifier: $messageId,
                extraHeaders: $this->providerHeaders($recipient),
            ));
        } catch (Throwable $exception) {
            return $this->recordFailure($campaign, $recipient, $mailerName, $exception);
        }

        $recipient->forceFill([
            'status' => CampaignRecipientStatus::Sent,
            'status_reason' => null,
            'message_id' => $messageId,
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ])->save();

        NewsletterDeliveryLog::query()->create([
            'newsletter_campaign_recipient_id' => $recipient->id,
            'newsletter_campaign_id' => $campaign->id,
            'attempt' => $recipient->attempts,
            'status' => 'submitted',
            'mailer' => $mailerName,
            'provider_message_id' => $providerMessageId,
        ]);

        return CampaignRecipientStatus::Sent;
    }

    private function ineligibilityReason(NewsletterCampaignRecipient $recipient): ?string
    {
        if (filter_var($recipient->email, FILTER_VALIDATE_EMAIL) === false) {
            $this->suppressions->suppress($recipient->email, SuppressionReason::Invalid, 'Malformed address found at send time');

            return 'Invalid email address.';
        }

        if ($this->suppressions->isSuppressed($recipient->email)) {
            return 'Address is suppressed.';
        }

        $subscriber = $recipient->newsletter_subscriber_id !== null
            ? NewsletterSubscriber::query()->sendable()->whereKey($recipient->newsletter_subscriber_id)->first()
            : null;

        if ($subscriber === null) {
            return 'No longer an active subscriber.';
        }

        if (NewsletterSubscriber::normalizeEmail($subscriber->email) !== $recipient->email) {
            return 'Subscriber email changed after the campaign was prepared.';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function providerHeaders(NewsletterCampaignRecipient $recipient): array
    {
        $headers = ['X-SES-MESSAGE-TAGS' => 'nl_recipient='.$recipient->token];

        if (filled(config('newsletter.ses_configuration_set'))) {
            $headers['X-SES-CONFIGURATION-SET'] = (string) config('newsletter.ses_configuration_set');
        }

        return $headers;
    }

    private function recordFailure(NewsletterCampaign $campaign, NewsletterCampaignRecipient $recipient, string $mailerName, Throwable $exception): CampaignRecipientStatus
    {
        // Admin-only diagnostics: the error class and a trimmed message.
        // The log line itself carries no address.
        Log::warning('Newsletter campaign email failed to send', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'attempt' => $recipient->attempts,
            'exception' => $exception->getMessage(),
        ]);

        NewsletterDeliveryLog::query()->create([
            'newsletter_campaign_recipient_id' => $recipient->id,
            'newsletter_campaign_id' => $campaign->id,
            'attempt' => $recipient->attempts,
            'status' => 'failed',
            'mailer' => $mailerName,
            'response' => Str::limit(class_basename($exception).': '.$exception->getMessage(), 1000),
        ]);

        if ($recipient->attempts < (int) config('newsletter.max_attempts', 3)) {
            $recipient->forceFill(['status' => CampaignRecipientStatus::Pending, 'status_reason' => 'Retrying after a send error.'])->save();

            return CampaignRecipientStatus::Pending;
        }

        $recipient->forceFill(['failed_at' => now()])->save();

        return $this->finish($recipient, CampaignRecipientStatus::Failed, 'Send failed after '.$recipient->attempts.' attempts.');
    }

    private function finish(NewsletterCampaignRecipient $recipient, CampaignRecipientStatus $status, string $reason): CampaignRecipientStatus
    {
        $recipient->forceFill(['status' => $status, 'status_reason' => $reason])->save();

        return $status;
    }
}
