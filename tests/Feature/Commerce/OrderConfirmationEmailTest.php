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
        $single = $this->readySingle();
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->create();
        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);

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

    public function test_the_default_order_confirmation_fills_every_purchaser_order_and_expiry_placeholder(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->create(['name' => 'John Smith']);
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email, $user->name);

        $this->payViaWebhook($order->public_id, 'evt_order_conf_vars', 'cs_order_conf_vars');

        $order->refresh();
        $expiresAt = $order->items->first()->entitlement->expires_at;

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($order, $single, $expiresAt): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'Greetings and gratitude, John,')
                && str_contains($rendered, 'Order #'.$order->public_id)
                && str_contains($rendered, '$'.number_format((float) $order->total, 2))
                && str_contains($rendered, route('account.orders'))
                && str_contains($rendered, route('music.singles.show', $single))
                && ($expiresAt === null || str_contains($rendered, $expiresAt->format('F j, Y')))
                && ! str_contains($rendered, '{{');
        });
    }

    public function test_the_default_guest_email_fills_every_purchaser_and_order_placeholder(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com', 'Jane Doe');

        $this->payViaWebhook($order->public_id, 'evt_guest_email_vars', 'cs_guest_email_vars');

        $order->refresh();
        $expiresAt = $order->items->first()->entitlement->expires_at;

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($order, $expiresAt): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'Greetings and gratitude, Jane,')
                && str_contains($rendered, e($order->items->first()->item_title))
                && str_contains($rendered, 'Order #'.$order->public_id)
                && str_contains($rendered, '$'.number_format((float) $order->total, 2))
                && str_contains($rendered, $order->paid_at->format('F j, Y'))
                && ($expiresAt === null || str_contains($rendered, $expiresAt->format('F j, Y')))
                && str_contains($rendered, route('register.show'))
                && ! str_contains($rendered, '{{');
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

    public function test_the_default_guest_email_for_an_album_includes_both_the_access_link_and_a_zip_link(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $album = $this->readyAlbum();
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com', 'Jane Doe');

        $this->payViaWebhook($order->public_id, 'evt_guest_album_zip', 'cs_guest_album_zip');

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($order): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'Download Now')
                && str_contains($rendered, 'Download Full Album (ZIP)')
                && str_contains($rendered, e(route('downloads.guest.show', $order)))
                && str_contains($rendered, 'next=album-zip')
                && ! str_contains($rendered, '{{');
        });
    }

    /**
     * The ZIP-link wording was added to these two keys' *defaults* — a
     * production row an admin has already customized (or one still holding
     * the previous default) must be left exactly as it is on re-seed; only
     * the code-owned available_variables list gains the new placeholder.
     */
    public function test_re_seeding_never_overwrites_customized_purchase_templates(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (['order_confirmation', 'guest_download_access'] as $key) {
            EmailTemplate::query()->where('notification_key', $key)->update([
                'subject' => "Custom subject for {$key}",
                'html_body' => "<p>Admin-written body for {$key} {{item_title}}</p>",
                'is_enabled' => false,
                'available_variables' => ['item_title'],
            ]);
        }

        $this->seed(EmailTemplateSeeder::class);

        foreach (['order_confirmation', 'guest_download_access'] as $key) {
            $template = EmailTemplate::query()->where('notification_key', $key)->sole();

            $this->assertSame("Custom subject for {$key}", $template->subject);
            $this->assertSame("<p>Admin-written body for {$key} {{item_title}}</p>", $template->html_body);
            $this->assertFalse((bool) $template->is_enabled);
            $this->assertContains('album_zip_download_url', $template->available_variables);
        }
    }

    public function test_the_default_order_confirmation_for_an_album_links_to_the_account_zip_section(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $user = User::factory()->create(['status' => 'active']);
        $album = $this->readyAlbum();
        $order = app(CreatePendingOrderAction::class)->handle($album, $user, $user->email);

        $this->payViaWebhook($order->public_id, 'evt_member_album_zip', 'cs_member_album_zip');

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => str_contains($mail->render(), route('account.orders').'#album-zip')
            && str_contains($mail->render(), 'Download Full Album (ZIP)'));
    }
}
