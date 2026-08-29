<?php

namespace Tests\Feature\Commerce;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Modules\Commerce\Actions\Cart\AddToCartAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Phase 4 — the two new purchase-email keys (order_confirmation,
 * guest_download_access), fired only from HandleCheckoutSessionCompletedAction's
 * `mode=payment` branch, once entitlements are actually newly issued (never
 * on a webhook retry — same idempotency guard MarkOrderPaidAction already
 * provides). The subscription branch (ProSubscriptionCheckoutWebhookTest)
 * must remain unaffected.
 */
class OrderConfirmationEmailTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    private function payViaWebhook(string $orderPublicId, string $eventId, string $sessionId): void
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $sessionId,
                'mode' => 'payment',
                'client_reference_id' => $orderPublicId,
                'payment_intent' => 'pi_'.$sessionId,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
    }

    public function test_a_registered_purchasers_paid_order_sends_order_confirmation_only(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->create();
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), $user, $user->email);

        $this->payViaWebhook($order->public_id, 'evt_order_conf_1', 'cs_order_conf_1');

        Mail::assertSent(TemplatedNotificationMail::class, 1);
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail) => $mail->hasTo($user->email));
    }

    public function test_a_guest_purchasers_paid_order_sends_guest_download_access_only(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $this->payViaWebhook($order->public_id, 'evt_guest_email_1', 'cs_guest_email_1');

        Mail::assertSent(TemplatedNotificationMail::class, 1);
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail) => $mail->hasTo('guest@example.com'));
    }

    public function test_the_guest_email_contains_the_secure_access_url(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        // The seeded default body (EmailTemplateSeeder::defaultHtmlBody())
        // is generic boilerplate with no {{download_access_url}} token —
        // exercise the same admin-configured-body path
        // EmailTemplateResourceTest uses, rather than asserting against
        // placeholder copy nobody wrote.
        EmailTemplate::query()
            ->where('notification_key', 'guest_download_access')
            ->update(['html_body' => '<p>Your downloads: {{download_access_url}}</p>']);

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $this->payViaWebhook($order->public_id, 'evt_guest_email_2', 'cs_guest_email_2');

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($order): bool {
            return $mail->hasTo('guest@example.com')
                && str_contains($mail->render(), route('downloads.guest.show', $order));
        });
    }

    public function test_a_multi_item_guest_order_sends_exactly_one_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $album = $this->readyAlbum();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');
        app(AddToCartAction::class)->handle($order, $single);

        $this->payViaWebhook($order->public_id, 'evt_guest_multi_1', 'cs_guest_multi_1');

        Mail::assertSent(TemplatedNotificationMail::class, 1);
    }

    public function test_replaying_the_webhook_event_does_not_resend_the_order_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $this->payViaWebhook($order->public_id, 'evt_guest_replay', 'cs_guest_replay');
        $this->payViaWebhook($order->public_id, 'evt_guest_replay', 'cs_guest_replay');

        Mail::assertSent(TemplatedNotificationMail::class, 1);
    }

    public function test_subscription_checkout_does_not_send_order_confirmation_or_guest_download_access(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->unverified()->create();

        $payload = json_encode([
            'id' => 'evt_sub_no_order_email',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_sub_no_order_email',
                'mode' => 'subscription',
                'client_reference_id' => (string) $user->id,
                'customer' => 'cus_1',
                'subscription' => 'sub_1',
                'currency' => 'usd',
                'amount_total' => 799,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);

        // Only the pre-existing pro_member_registered + email_verification
        // pair — never order_confirmation/guest_download_access.
        Mail::assertSent(TemplatedNotificationMail::class, 2);
    }
}
