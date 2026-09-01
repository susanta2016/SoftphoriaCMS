<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Actions\Cart\AddToCartAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Exceptions\PurchaseNotReadyException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Modules\Music\Support\CartSession;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Music is digital-only: no shipping address, no shipping method, nothing to
 * fulfil beyond payment. The cart (CartController/CartSession) is
 * session-only up to this point — this is where it finally becomes a real
 * Commerce Order, because that's also the first point a purchaser's email is
 * actually known (their account email if registered, or the guest form
 * below). A registered user skips the guest fields entirely; a guest must
 * provide name/email here, never earlier and never by being forced to
 * register.
 */
class CheckoutController extends Controller
{
    public function show(SettingsRepository $settings, CartController $cart): View|RedirectResponse
    {
        $lines = $cart->hydratedCartLines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.show')->with('cart_error', 'Your cart is empty.');
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Checkout — {$chrome['siteName']}",
            'canonical' => route('checkout.show'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('checkout.show', [
            ...$chrome,
            'seo' => $seo,
            'lines' => $lines,
            'subtotal' => $lines->sum('price'),
        ]);
    }

    public function process(
        Request $request,
        CartController $cart,
        CreatePendingOrderAction $createOrder,
        AddToCartAction $addToCart,
        StripeGatewayContract $stripe,
    ): RedirectResponse {
        $lines = $cart->hydratedCartLines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.show')->with('cart_error', 'Your cart is empty.');
        }

        $user = Auth::user();

        if ($user !== null) {
            $purchaserEmail = $user->email;
            $purchaserName = $user->name;
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ]);
            $purchaserEmail = $data['email'];
            $purchaserName = $data['name'];
        }

        try {
            $order = null;

            foreach ($lines as $line) {
                $order = $order === null
                    ? $createOrder->handle($line['model'], $user, $purchaserEmail, $purchaserName)
                    : $addToCart->handle($order, $line['model']);
            }
        } catch (PurchaseNotReadyException $e) {
            return redirect()->route('cart.show')->with('cart_error', $e->getMessage());
        }

        $successUrl = route('checkout.return', $order).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('cart.show');

        try {
            $checkoutUrl = $stripe->createCheckoutSessionForOrder($order, $successUrl, $cancelUrl);
        } catch (Throwable $e) {
            Log::error('Music checkout: failed to create Stripe Checkout Session', [
                'order_public_id' => $order->public_id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('cart.show')->with('cart_error', 'We couldn\'t start checkout just now. Please try again in a moment.');
        }

        CartSession::clear();

        return redirect()->away($checkoutUrl);
    }

    /**
     * Stripe's success redirect. Never trusts the redirect itself (or
     * session_id) to mean payment succeeded — the webhook
     * (HandleCheckoutSessionCompletedAction -> MarkOrderPaidAction) is the
     * only writer of Order::status, exactly like
     * RegistrationController::proComplete() never re-calls Stripe either.
     * A webhook can lag behind the redirect by a few seconds, so a still-
     * "pending" Order here isn't an error — it's just processing.
     */
    public function returnPage(Order $order, SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Order Confirmation — {$chrome['siteName']}",
            'canonical' => url()->current(),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('checkout.return', [
            ...$chrome,
            'seo' => $seo,
            'order' => $order->load('items'),
        ]);
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
