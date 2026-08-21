<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Commerce\Actions\Entitlement\ResolveTrackAccessAction;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\SubscriptionDisplayStatus;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Client-confirmed rule: a Pro Member who cancels before the next billing
 * date keeps full Pro access — including unlimited downloads — until the
 * end of the already-paid current billing period. "Cancel" always means
 * "cancel at period end," never immediate revocation.
 */
class SubscriptionCancellationTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_canceling_an_active_subscription_keeps_it_active_until_period_end(): void
    {
        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
            'cancel_at_period_end' => false,
        ]);

        $this->assertTrue($subscription->isActive());

        // Cancelling: cancel_at_period_end=true, canceled_at recorded —
        // status is deliberately left untouched (Stripe itself keeps it
        // 'active' through the grace window).
        $subscription->cancel_at_period_end = true;
        $subscription->cancelled_at = now();
        $subscription->save();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertTrue($subscription->refresh()->isActive());
        $this->assertSame(SubscriptionDisplayStatus::CancelingAtPeriodEnd, $subscription->displayStatus());
    }

    public function test_cancel_at_period_end_alone_never_revokes_access(): void
    {
        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(5),
            'cancel_at_period_end' => false,
        ]);

        $this->assertTrue($subscription->isActive());

        // Flip only cancel_at_period_end — status and current_period_end
        // untouched. isActive() must not move.
        $subscription->cancel_at_period_end = true;
        $subscription->save();

        $this->assertTrue($subscription->refresh()->isActive());
    }

    public function test_a_cancelled_subscription_can_still_download_during_the_remaining_paid_period(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(10),
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
        ]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);
        $this->assertNotNull($grant);
        $this->assertSame(DownloadAccessType::Membership, $grant->type);

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForUser($single->track, $user);
        $this->assertTrue($result->authorized);
    }

    public function test_access_stops_after_current_period_end_without_renewal(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        // Simulates a webhook-lag scenario too: status still says 'active'
        // but the paid period has genuinely passed with no renewal.
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->subDay(),
            'cancel_at_period_end' => true,
            'cancelled_at' => now()->subDays(30),
        ]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);
        $this->assertNull($grant);

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForUser($single->track, $user);
        $this->assertFalse($result->authorized);
        $this->assertSame('not_entitled', $result->denialReason);
    }

    public function test_access_stops_once_stripe_reports_the_subscription_fully_ended(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Canceled,
            'current_period_end' => now()->addDays(5), // even mid-period —
            'ended_at' => now(),
        ]);

        // `status` — not just the date — governs access: Stripe reporting
        // the subscription as fully ended (customer.subscription.deleted)
        // stops access immediately, matching Stripe's own semantics.
        $this->assertNull(app(ResolveTrackAccessAction::class)->forUser($single->track, $user));
    }

    public function test_successful_renewal_extends_the_period_and_keeps_the_subscription_active(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_renew_1',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDay(), // about to expire
            'cancel_at_period_end' => false,
        ]);

        $newPeriodEnd = now()->addDays(30);

        $payload = json_encode([
            'id' => 'evt_renewed',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_renew_1',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'current_period_start' => now()->timestamp,
                'current_period_end' => $newPeriodEnd->timestamp,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $subscription->refresh();
        $this->assertTrue($subscription->isActive());
        $this->assertEquals($newPeriodEnd->timestamp, $subscription->current_period_end->timestamp);
        $this->assertFalse($subscription->cancel_at_period_end);
    }

    public function test_a_renewal_after_a_prior_cancellation_clears_the_cancellation_state(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_resumed_1',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(3),
            'cancel_at_period_end' => true,
            'cancelled_at' => now()->subDays(2),
        ]);

        // Customer resumed via Stripe's billing portal before the period
        // ended — Stripe reports cancel_at_period_end/canceled_at cleared.
        $payload = json_encode([
            'id' => 'evt_resumed',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_resumed_1',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'current_period_end' => now()->addDays(3)->timestamp,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $subscription->refresh();
        $this->assertFalse($subscription->cancel_at_period_end);
        $this->assertNull($subscription->cancelled_at);
        $this->assertSame(SubscriptionDisplayStatus::Active, $subscription->displayStatus());
    }

    public function test_stripe_cancel_at_period_end_webhook_synchronizes_without_dropping_access(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $periodEnd = now()->addDays(15);

        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_cancel_webhook_1',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);

        $canceledAt = now();

        $payload = json_encode([
            'id' => 'evt_cancel_at_period_end',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_cancel_webhook_1',
                'status' => 'active', // Stripe keeps this 'active' through the grace window
                'cancel_at_period_end' => true,
                'canceled_at' => $canceledAt->timestamp,
                'current_period_end' => $periodEnd->timestamp,
            ]],
        ]);

        $response = $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
        $response->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isActive());
        $this->assertSame(SubscriptionDisplayStatus::CancelingAtPeriodEnd, $subscription->displayStatus());
    }

    public function test_invoice_payment_succeeded_records_a_renewal_transaction(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'stripe_subscription_id' => 'sub_invoice_1',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(30),
        ]);

        $payload = json_encode([
            'id' => 'evt_invoice_paid',
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'id' => 'in_test_1',
                'subscription' => 'sub_invoice_1',
                'customer' => 'cus_test_1',
                'amount_paid' => 799,
                'currency' => 'usd',
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $transaction = PaymentTransaction::query()->where('provider_event_id', 'evt_invoice_paid')->first();
        $this->assertNotNull($transaction);
        $this->assertSame('7.99', (string) $transaction->amount);

        // Idempotent replay.
        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
        $this->assertSame(1, PaymentTransaction::query()->where('provider_event_id', 'evt_invoice_paid')->count());
    }
}
