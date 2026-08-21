<?php

namespace App\Modules\Commerce\Services\Stripe;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\StripeEvent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Real implementation of StripeGatewayContract, backed by stripe/stripe-php.
 * Bound in AppServiceProvider/CommerceServiceProvider; FakeStripeGateway is
 * bound instead in tests. Price is always the caller's already-resolved
 * Global Pricing value (see GlobalPricingResolver) — this class never reads
 * a price itself, only ever passes one through to Stripe as `price_data`, so
 * there is no Stripe Product/Price object to keep in sync with Website
 * Setup.
 */
class StripeGateway implements StripeGatewayContract
{
    public function __construct(private readonly StripeClient $client) {}

    public function verifyAndParseWebhook(string $payload, string $signature): StripeEvent
    {
        $event = Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));

        return new StripeEvent($event->id, $event->type, $event->data->object->toArray());
    }

    public function createCheckoutSessionForOrder(Order $order, string $successUrl, string $cancelUrl): string
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->isGuest() ? $order->purchaser_email : null,
            'client_reference_id' => $order->public_id,
            'line_items' => [[
                'price_data' => [
                    'currency' => $order->currency,
                    'unit_amount' => (int) round(((float) $order->total) * 100),
                    'product_data' => ['name' => $order->items->first()?->item_title ?? 'Music purchase'],
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['order_public_id' => $order->public_id],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return $session->url;
    }

    public function createSubscriptionCheckoutSession(User $user, string $priceAmount, string $successUrl, string $cancelUrl): string
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'client_reference_id' => (string) $user->getKey(),
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) round(((float) $priceAmount) * 100),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => ['name' => 'Pro Member subscription'],
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['user_id' => (string) $user->getKey()],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return $session->url;
    }
}
