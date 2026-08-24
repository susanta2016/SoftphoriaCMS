<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §4/§18/§22 of the approved brief — Stripe events actually synchronizing
 * application-owned Order/Entitlement/Subscription state, and rejecting
 * anything that doesn't verify. FakeStripeGateway stands in for the real
 * SDK so this never touches the network.
 */
class StripeWebhookTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_an_invalid_signature_is_rejected_and_nothing_is_processed(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $response = $this->withHeader('Stripe-Signature', 'invalid-signature')
            ->post('/commerce/webhooks/stripe', [], ['Content-Type' => 'application/json']);

        $response->assertStatus(400);
        $this->assertSame(0, PaymentTransaction::query()->count());
    }

    public function test_checkout_session_completed_marks_the_order_paid_and_issues_an_entitlement(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $payload = json_encode([
            'id' => 'evt_checkout_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'mode' => 'payment',
                'client_reference_id' => $order->public_id,
                'payment_intent' => 'pi_test_123',
            ]],
        ]);

        $response = $this->withHeader('Stripe-Signature', 'valid')
            ->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->items->first()->entitlement);
    }

    public function test_replaying_the_same_checkout_event_does_not_double_process(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $payload = json_encode([
            'id' => 'evt_checkout_dup',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_dup',
                'mode' => 'payment',
                'client_reference_id' => $order->public_id,
                'payment_intent' => 'pi_test_dup',
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $this->assertSame(1, PaymentTransaction::query()->where('provider_event_id', 'evt_checkout_dup')->count());
        $this->assertSame(1, $order->items->first()->entitlement()->count());
    }

    public function test_subscription_updated_transitions_status(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = $this->admin();
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'stripe_subscription_id' => 'sub_test_1',
            'status' => SubscriptionStatus::Active,
        ]);

        $payload = json_encode([
            'id' => 'evt_sub_updated',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_test_1',
                'status' => 'past_due',
                'cancel_at_period_end' => false,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $this->assertSame(SubscriptionStatus::PastDue, Subscription::query()->where('stripe_subscription_id', 'sub_test_1')->first()->status);
    }

    /**
     * Recent Stripe API versions moved current_period_start/end off the
     * top-level Subscription object onto its first line item — confirmed
     * against a live account, where a top-level-only read left both columns
     * permanently null (see HandleSubscriptionUpdatedAction's docblock).
     * This is the shape a real webhook payload actually has today.
     */
    public function test_subscription_updated_reads_the_period_from_the_nested_line_item(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_item_period_1',
            'status' => SubscriptionStatus::Active,
        ]);

        $periodStart = now();
        $periodEnd = now()->addDays(30);

        $payload = json_encode([
            'id' => 'evt_item_period',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_item_period_1',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => ['data' => [[
                    'current_period_start' => $periodStart->timestamp,
                    'current_period_end' => $periodEnd->timestamp,
                ]]],
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $subscription->refresh();
        $this->assertEquals($periodStart->timestamp, $subscription->current_period_start->timestamp);
        $this->assertEquals($periodEnd->timestamp, $subscription->current_period_end->timestamp);
    }

    /**
     * `customer.subscription.created` fires once at signup, before any
     * renewal — routing it through the same handler as `updated` is what
     * populates current_period_end immediately rather than leaving it null
     * for the whole first billing month.
     */
    public function test_subscription_created_populates_the_initial_period(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_created_1',
            'status' => SubscriptionStatus::Active,
        ]);

        $periodEnd = now()->addDays(30);

        $payload = json_encode([
            'id' => 'evt_sub_created',
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => 'sub_created_1',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => ['data' => [[
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => $periodEnd->timestamp,
                ]]],
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $this->assertEquals($periodEnd->timestamp, $subscription->refresh()->current_period_end->timestamp);
    }
}
