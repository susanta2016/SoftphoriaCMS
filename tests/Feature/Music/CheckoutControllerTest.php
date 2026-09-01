<?php

namespace Tests\Feature\Music;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Modules\Commerce\Support\StripeEvent;
use App\Modules\Music\Support\CartSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Checkout is where the session-only cart finally becomes a real Commerce
 * Order — see CheckoutController's docblock. Stripe is faked throughout
 * (see FakeStripeGateway, also used by ProRegistrationTest/StripeWebhookTest)
 * so these tests never hit the network.
 */
class CheckoutControllerTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_visiting_checkout_with_an_empty_cart_redirects_to_the_cart_page(): void
    {
        $response = $this->get(route('checkout.show'));

        $response->assertRedirect(route('cart.show'));
    }

    public function test_submitting_checkout_with_an_empty_cart_redirects_to_the_cart_page(): void
    {
        $response = $this->post(route('checkout.process'), []);

        $response->assertRedirect(route('cart.show'));
    }

    public function test_a_guest_must_provide_name_and_email_to_checkout(): void
    {
        $album = $this->readyAlbum();
        CartSession::add('album', $album->getKey());

        $response = $this->post(route('checkout.process'), []);

        $response->assertSessionHasErrors(['name', 'email']);
    }

    public function test_a_guest_checking_out_creates_a_pending_order_and_redirects_to_stripe(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);
        $album = $this->readyAlbum();
        CartSession::add('album', $album->getKey());

        $response = $this->post(route('checkout.process'), [
            'name' => 'Guest Person',
            'email' => 'guest@example.com',
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('guest@example.com', $order->purchaser_email);
        $this->assertSame('Guest Person', $order->purchaser_name);
        $this->assertNull($order->purchaser_phone);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(1, $order->items->count());
        $this->assertSame('9.99', (string) $order->total);

        $response->assertRedirect('https://checkout.stripe.test/fake-session');
        $this->assertSame(0, CartSession::count());
    }

    public function test_a_registered_user_checks_out_using_their_account_email_without_a_form(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);
        $user = $this->admin();
        $single = $this->readySingle();
        CartSession::add('single', $single->getKey());

        $response = $this->actingAs($user)->post(route('checkout.process'), []);

        $order = Order::query()->latest('id')->first();
        $this->assertSame($user->email, $order->purchaser_email);
        $this->assertSame($user->getKey(), $order->user_id);
        $response->assertRedirect('https://checkout.stripe.test/fake-session');
    }

    public function test_multiple_cart_items_are_combined_into_a_single_order(): void
    {
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);
        $user = $this->admin();
        $album = $this->readyAlbum();
        $single = $this->readySingle();
        CartSession::add('album', $album->getKey());
        CartSession::add('single', $single->getKey());

        $this->actingAs($user)->post(route('checkout.process'), []);

        $order = Order::query()->latest('id')->first();
        $this->assertSame(2, $order->items->count());
        $this->assertSame('10.98', (string) $order->total);
    }

    public function test_a_stripe_failure_returns_to_the_cart_with_an_error_and_keeps_the_cart_intact(): void
    {
        $failingGateway = new class implements StripeGatewayContract
        {
            public function verifyAndParseWebhook(string $payload, string $signature): StripeEvent
            {
                throw new \RuntimeException('not used');
            }

            public function createCheckoutSessionForOrder(Order $order, string $successUrl, string $cancelUrl): string
            {
                throw new \RuntimeException('Stripe is down');
            }

            public function createEmbeddedSubscriptionCheckoutSession(User $user, string $priceAmount, string $returnUrl): string
            {
                throw new \RuntimeException('not used');
            }
        };
        $this->app->instance(StripeGatewayContract::class, $failingGateway);

        $user = $this->admin();
        $single = $this->readySingle();
        CartSession::add('single', $single->getKey());

        $response = $this->actingAs($user)->post(route('checkout.process'), []);

        $response->assertRedirect(route('cart.show'));
        $response->assertSessionHas('cart_error');
        $this->assertSame(1, CartSession::count());
    }

    public function test_the_return_page_shows_a_pending_state_before_the_webhook_confirms_payment(): void
    {
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)
            ->handle($single, null, 'guest@example.com');

        $response = $this->get(route('checkout.return', $order));

        $response->assertOk();
        $response->assertSee('Confirming your payment');
    }

    public function test_the_return_page_shows_a_success_state_once_the_order_is_paid(): void
    {
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)
            ->handle($single, null, 'guest@example.com');
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $response = $this->get(route('checkout.return', $order));

        $response->assertOk();
        $response->assertSee('Thank you for your purchase');
    }
}
