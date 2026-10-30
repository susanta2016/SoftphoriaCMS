<?php

namespace App\Modules\Newsletter\Actions;

use App\Modules\Newsletter\Enums\NewsletterEventType;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use Illuminate\Support\Carbon;

/**
 * Maps an already signature-verified SES event (from
 * App\Http\Controllers\Newsletter\SesWebhookController) onto the campaign
 * recipient it concerns, if it concerns one: matched by the nl_recipient
 * message tag NewsletterDeliveryService adds, falling back to the provider
 * message id. Events for non-campaign mail (transactional email, the
 * footer signup's own messages) are ignored here — the controller's
 * existing bounce/complaint suppression still handles those.
 *
 * Runs whether or not the module is switched on: it only records what
 * happened to email already sent, and dropping bounce data would hurt
 * sender reputation.
 */
class HandleSesCampaignEventAction
{
    public function __construct(private readonly RecordNewsletterEventAction $record) {}

    /**
     * @param  array<string, mixed>  $event  the decoded SES event/notification
     */
    public function handle(array $event): void
    {
        $recipient = $this->recipientFor((array) ($event['mail'] ?? []));

        if ($recipient === null) {
            return;
        }

        $kind = (string) ($event['eventType'] ?? $event['notificationType'] ?? '');
        $messageId = (string) data_get($event, 'mail.messageId', '');

        [$type, $payload] = match ($kind) {
            'Delivery' => [NewsletterEventType::Delivery, (array) ($event['delivery'] ?? [])],
            'Bounce' => [
                data_get($event, 'bounce.bounceType') === 'Permanent' ? NewsletterEventType::Bounce : NewsletterEventType::SoftBounce,
                (array) ($event['bounce'] ?? []),
            ],
            'Complaint' => [NewsletterEventType::Complaint, (array) ($event['complaint'] ?? [])],
            'Open' => [NewsletterEventType::Open, (array) ($event['open'] ?? [])],
            'Click' => [NewsletterEventType::Click, (array) ($event['click'] ?? [])],
            'Reject' => [NewsletterEventType::Reject, (array) ($event['reject'] ?? [])],
            'DeliveryDelay' => [NewsletterEventType::DeliveryDelay, (array) ($event['deliveryDelay'] ?? [])],
            'Rendering Failure' => [NewsletterEventType::RenderingFailure, (array) ($event['failure'] ?? [])],
            default => [null, []],
        };

        if ($type === null) {
            return;
        }

        $timestamp = (string) ($payload['timestamp'] ?? data_get($event, 'mail.timestamp', ''));
        $detail = match ($type) {
            NewsletterEventType::Click => (string) ($payload['link'] ?? ''),
            NewsletterEventType::Bounce, NewsletterEventType::SoftBounce => (string) ($payload['bounceSubType'] ?? ''),
            NewsletterEventType::Complaint => (string) ($payload['complaintFeedbackType'] ?? ''),
            NewsletterEventType::Reject => (string) ($payload['reason'] ?? ''),
            default => null,
        };

        $this->record->handle(
            $recipient,
            $type,
            'ses',
            implode('|', ['ses', $messageId, $type->value, $timestamp, (string) $detail]),
            $timestamp !== '' ? Carbon::parse($timestamp) : now(),
            $detail ?: null,
        );
    }

    /**
     * @param  array<string, mixed>  $mail
     */
    private function recipientFor(array $mail): ?NewsletterCampaignRecipient
    {
        $token = data_get($mail, 'tags.nl_recipient.0');

        if (is_string($token) && $token !== '') {
            return NewsletterCampaignRecipient::query()->where('token', $token)->first();
        }

        $messageId = $mail['messageId'] ?? null;

        return is_string($messageId) && $messageId !== ''
            ? NewsletterCampaignRecipient::query()->where('provider_message_id', $messageId)->first()
            : null;
    }
}
