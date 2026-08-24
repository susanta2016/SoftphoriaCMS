<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Actions\Webhook\HandleChargeRefundedAction;
use App\Modules\Commerce\Actions\Webhook\HandleCheckoutSessionCompletedAction;
use App\Modules\Commerce\Actions\Webhook\HandleInvoicePaymentFailedAction;
use App\Modules\Commerce\Actions\Webhook\HandleInvoicePaymentSucceededAction;
use App\Modules\Commerce\Actions\Webhook\HandleSubscriptionDeletedAction;
use App\Modules\Commerce\Actions\Webhook\HandleSubscriptionUpdatedAction;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;

/**
 * The one inbound Stripe integration point in this task — receives and
 * synchronizes application-owned state (§4/§18/§I of the plan: the
 * application, not Stripe, is the source of truth for order/entitlement/
 * subscription state). Signature verification happens before anything else
 * runs; an invalid signature is rejected with nothing processed. Excluded
 * from CSRF verification (see bootstrap/app.php) the same way any webhook
 * endpoint must be — Stripe cannot supply a CSRF token, and the signature
 * check is the actual authenticity guarantee here.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeGatewayContract $stripe,
        private readonly HandleCheckoutSessionCompletedAction $handleCheckoutSessionCompleted,
        private readonly HandleSubscriptionUpdatedAction $handleSubscriptionUpdated,
        private readonly HandleSubscriptionDeletedAction $handleSubscriptionDeleted,
        private readonly HandleInvoicePaymentFailedAction $handleInvoicePaymentFailed,
        private readonly HandleInvoicePaymentSucceededAction $handleInvoicePaymentSucceeded,
        private readonly HandleChargeRefundedAction $handleChargeRefunded,
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $event = $this->stripe->verifyAndParseWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException) {
            return response('Invalid signature.', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted->handle($event),
            // Both share HandleSubscriptionUpdatedAction: `created` fires
            // once at signup (this is what actually populates
            // current_period_start/end immediately, rather than leaving
            // them null until the first `updated` some 30 days later) and
            // `updated` fires on every later status/period/cancellation
            // change — same Subscription object shape either way.
            'customer.subscription.created', 'customer.subscription.updated' => $this->handleSubscriptionUpdated->handle($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted->handle($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed->handle($event),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded->handle($event),
            'charge.refunded' => $this->handleChargeRefunded->handle($event),
            default => null,
        };

        return response('OK', 200);
    }
}
