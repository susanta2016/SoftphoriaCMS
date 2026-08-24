<?php

namespace Tests\Feature\Commerce;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The webhook side of Pro registration (points 4/5/9 of the confirmed
 * spec): `checkout.session.completed` (mode=subscription) is what actually
 * activates a Subscription and — only the first time, for a genuinely new
 * signup — triggers the Pro welcome/verification email, snapshotting the
 * real amount Stripe charged. This is the sole source of truth for
 * subscription state; nothing in the registration request path itself
 * writes to `subscriptions`.
 */
class ProSubscriptionCheckoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_confirmed_subscription_checkout_activates_the_subscription_and_snapshots_price_and_currency(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);

        $payload = json_encode([
            'id' => 'evt_pro_signup_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_pro_1',
                'mode' => 'subscription',
                'client_reference_id' => (string) $user->id,
                'customer' => 'cus_pro_1',
                'subscription' => 'sub_pro_1',
                'currency' => 'usd',
                'amount_total' => 799,
            ]],
        ]);

        $response = $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
        $response->assertOk();

        $subscription = Subscription::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($subscription->isActive());
        $this->assertSame('usd', $subscription->currency);
        $this->assertSame('7.99', (string) $subscription->price_at_subscription);
        $this->assertSame('cus_pro_1', $subscription->stripe_customer_id);
        $this->assertSame('sub_pro_1', $subscription->stripe_subscription_id);

        // User account state is untouched by the webhook — still
        // PendingVerification until the separate email-verification step.
        $this->assertSame(UserStatus::PendingVerification->value, $user->fresh()->status);

        $this->assertSame(1, EmailVerification::query()->where('user_id', $user->id)->count());
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_replaying_the_same_subscription_checkout_event_does_not_resend_the_welcome_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);

        $payload = json_encode([
            'id' => 'evt_pro_signup_dup',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_pro_dup',
                'mode' => 'subscription',
                'client_reference_id' => (string) $user->id,
                'currency' => 'usd',
                'amount_total' => 799,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        $this->assertSame(1, PaymentTransaction::query()->where('provider_event_id', 'evt_pro_signup_dup')->count());
        // Two distinct emails (pro_member_registered + email_verification)
        // per genuine trigger — the replay must add zero more, not zero
        // total.
        Mail::assertSent(TemplatedNotificationMail::class, 2);
    }

    public function test_a_second_distinct_checkout_for_an_already_subscribed_user_does_not_resend_the_welcome_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);

        $firstPayload = json_encode([
            'id' => 'evt_pro_signup_first',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_first', 'mode' => 'subscription', 'client_reference_id' => (string) $user->id, 'currency' => 'usd', 'amount_total' => 799]],
        ]);
        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $firstPayload);

        Mail::assertSent(TemplatedNotificationMail::class, 2);

        // A genuinely different event (e.g. a resubscribe) for the same
        // already-existing Subscription row — firstOrNew() finds it, so
        // this is not a "just registered" moment and must not re-send.
        $secondPayload = json_encode([
            'id' => 'evt_pro_signup_second',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_second', 'mode' => 'subscription', 'client_reference_id' => (string) $user->id, 'currency' => 'usd', 'amount_total' => 799]],
        ]);
        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $secondPayload);

        // Still just the 2 from the first event — the second, distinct
        // event found an existing Subscription row (firstOrNew), so it was
        // never "just registered" and must not trigger the welcome email
        // again.
        Mail::assertSent(TemplatedNotificationMail::class, 2);
    }
}
