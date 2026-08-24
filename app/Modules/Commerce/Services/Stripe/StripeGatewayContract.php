<?php

namespace App\Modules\Commerce\Services\Stripe;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\StripeEvent;
use Stripe\Exception\SignatureVerificationException;

/**
 * The one boundary every other Commerce class talks to instead of the
 * stripe-php SDK directly (§5/§10 of the approved brief: "Stripe should be
 * implemented behind a service/provider abstraction... do not put
 * Stripe-specific business logic directly into Music models" — and nowhere
 * else, either). StripeGateway is the real implementation; FakeStripeGateway
 * (bound in tests) lets every webhook/checkout test run without a network
 * call. Checkout-session creation is part of the contract now — ready for a
 * future checkout controller to call directly — even though no route calls
 * it in this task (§11: no frontend checkout in this pass).
 */
interface StripeGatewayContract
{
    /**
     * @throws SignatureVerificationException
     */
    public function verifyAndParseWebhook(string $payload, string $signature): StripeEvent;

    /**
     * Returns the Stripe-hosted Checkout URL to redirect the purchaser to.
     */
    public function createCheckoutSessionForOrder(Order $order, string $successUrl, string $cancelUrl): string;

    /**
     * Embedded Checkout (`ui_mode: 'embedded_page'`) — card entry renders inline
     * on our own page via Stripe.js, never a redirect to Stripe's hosted
     * page, and raw card data never reaches Laravel. Returns the session's
     * `client_secret` for the frontend to mount, not a URL. $returnUrl is
     * where Stripe sends the browser once checkout completes inside the
     * embed; it must contain the literal `{CHECKOUT_SESSION_ID}` placeholder
     * (Stripe substitutes it) — see RegisterProUserAction. Still the same
     * Checkout Session/`checkout.session.completed` webhook architecture as
     * createCheckoutSessionForOrder() above, just a different `ui_mode`.
     */
    public function createEmbeddedSubscriptionCheckoutSession(User $user, string $priceAmount, string $returnUrl): string;
}
