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

    public function createSubscriptionCheckoutSession(User $user, string $priceAmount, string $successUrl, string $cancelUrl): string;
}
