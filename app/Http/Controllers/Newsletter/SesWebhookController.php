<?php

namespace App\Http\Controllers\Newsletter;

use App\Actions\Newsletter\Webhook\HandleSesBounceAction;
use App\Actions\Newsletter\Webhook\HandleSesComplaintAction;
use App\Http\Controllers\Controller;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The one inbound SES/SNS integration point (docs/SES-SNS-SETUP.md) —
 * receives the bounce/complaint feedback loop Amazon SES publishes through
 * an SNS topic. Mirrors App\Modules\Commerce\Http\Controllers\
 * StripeWebhookController's shape: signature verification happens before
 * anything else runs, using AWS's own aws/aws-php-sns-message-validator
 * (never a homemade signature check — see the package's own MessageValidator
 * for the documented AWS algorithm this delegates to). Excluded from CSRF
 * verification (see bootstrap/app.php) the same way any webhook endpoint
 * must be — SNS cannot supply a CSRF token, and the signature check is the
 * actual authenticity guarantee here.
 *
 * A valid signature only proves "this came from some real SNS topic," not
 * "this came from *our* topic" — anyone can create their own SNS topic and
 * point it at this URL. SES_SNS_TOPIC_ARN (config('services.ses.sns_topic_arn'))
 * closes that gap when configured; it's optional so local/testing
 * environments without a real topic still work.
 */
class SesWebhookController extends Controller
{
    public function __construct(
        private readonly MessageValidator $validator,
        private readonly HandleSesBounceAction $handleBounce,
        private readonly HandleSesComplaintAction $handleComplaint,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response('Invalid payload.', 400);
        }

        try {
            $message = new Message($payload);
            $this->validator->validate($message);
        } catch (InvalidArgumentException|InvalidSnsMessageException $exception) {
            // Never the full payload — it can contain a subscriber's email
            // address (Step 14: no unnecessary personal data in logs).
            Log::warning('Newsletter SES webhook: rejected an unverifiable SNS message', [
                'error' => $exception->getMessage(),
            ]);

            return response('Invalid signature.', 400);
        }

        $expectedTopicArn = config('services.ses.sns_topic_arn');

        if (filled($expectedTopicArn) && $message['TopicArn'] !== $expectedTopicArn) {
            Log::warning('Newsletter SES webhook: message from an unexpected SNS topic rejected');

            return response('Unexpected topic.', 400);
        }

        return match ($message['Type']) {
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => $this->confirmSnsSubscription($message),
            'Notification' => $this->processNotification($message),
            default => response('OK', 200),
        };
    }

    /**
     * Step 3 of the SNS subscription lifecycle: SNS already proved (via the
     * signature check above) that it really issued this SubscribeURL, so
     * hitting it is safe — it's the one AWS-documented way to activate an
     * HTTPS SNS subscription.
     */
    private function confirmSnsSubscription(Message $message): Response
    {
        try {
            Http::get((string) $message['SubscribeURL']);
        } catch (Throwable $exception) {
            Log::warning('Newsletter SES webhook: failed to confirm the SNS subscription', [
                'error' => $exception->getMessage(),
            ]);

            return response('Could not confirm subscription.', 500);
        }

        Log::info('Newsletter SES webhook: SNS subscription confirmed', [
            'topic_arn' => $message['TopicArn'] ?? null,
        ]);

        return response('OK', 200);
    }

    private function processNotification(Message $message): Response
    {
        $sesEvent = json_decode((string) $message['Message'], true);

        if (! is_array($sesEvent)) {
            Log::warning('Newsletter SES webhook: notification body was not valid JSON', [
                'message_id' => $message['MessageId'] ?? null,
            ]);

            return response('OK', 200);
        }

        $eventType = $sesEvent['eventType'] ?? $sesEvent['notificationType'] ?? null;

        Log::info('Newsletter SES webhook: processing notification', [
            'message_id' => $message['MessageId'] ?? null,
            'event_type' => $eventType,
        ]);

        match ($eventType) {
            'Bounce' => $this->handleBounce->handle($sesEvent),
            'Complaint' => $this->handleComplaint->handle($sesEvent),
            default => Log::info('Newsletter SES webhook: unsupported event type ignored', [
                'event_type' => $eventType,
            ]),
        };

        return response('OK', 200);
    }
}
