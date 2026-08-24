<?php

namespace App\Modules\Commerce\Services\Stripe;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\StripeEvent;
use Stripe\Exception\SignatureVerificationException;

/**
 * Test double bound in place of StripeGateway — see
 * tests/Feature/Commerce/StripeWebhookTest.php. verifyAndParseWebhook()
 * expects $payload to already be a JSON-encoded {id, type, data} shape (no
 * real signature is computed); pass the literal string 'invalid-signature'
 * as $signature to exercise the rejection path.
 */
class FakeStripeGateway implements StripeGatewayContract
{
    /** @var array<int, array{order: Order, successUrl: string, cancelUrl: string}> */
    public array $checkoutSessionsCreated = [];

    /** @var array<int, array{user: User, priceAmount: string, returnUrl: string}> */
    public array $subscriptionSessionsCreated = [];

    public function verifyAndParseWebhook(string $payload, string $signature): StripeEvent
    {
        if ($signature === 'invalid-signature') {
            throw SignatureVerificationException::factory('Invalid signature.');
        }

        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return new StripeEvent($decoded['id'], $decoded['type'], $decoded['data']['object'] ?? []);
    }

    public function createCheckoutSessionForOrder(Order $order, string $successUrl, string $cancelUrl): string
    {
        $this->checkoutSessionsCreated[] = compact('order', 'successUrl', 'cancelUrl');

        return 'https://checkout.stripe.test/fake-session';
    }

    public function createEmbeddedSubscriptionCheckoutSession(User $user, string $priceAmount, string $returnUrl): string
    {
        $this->subscriptionSessionsCreated[] = compact('user', 'priceAmount', 'returnUrl');

        return 'cs_test_fake_client_secret_'.$user->getKey();
    }
}
