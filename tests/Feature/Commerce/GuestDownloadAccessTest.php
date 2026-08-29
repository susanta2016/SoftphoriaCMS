<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Cart\AddToCartAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Phase 4 — the guest download-access flow reached via
 * GuestDownloadController, sitting entirely in front of the existing,
 * unmodified ResolveTrackAccessAction::forGuestToken()/
 * AuthorizeTrackDownloadAction::authorizeForGuest(). Two independent gates
 * are asserted throughout: token possession (session-captured from the
 * emailed link) and purchase-email knowledge — neither alone is ever
 * sufficient.
 */
class GuestDownloadAccessTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    private function payForGuestOrder(string $email = 'guest@example.com'): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, null, $email);
        $unique = uniqid();
        $issued = app(MarkOrderPaidAction::class)->handle($order, "pi_{$unique}", "evt_{$unique}");

        return [$order->refresh(), $issued, $single];
    }

    private function accessUrl(Order $order, array $issued): string
    {
        $pairs = collect($issued)->map(fn ($i) => "{$i->entitlement->public_id}.{$i->plainGuestToken}")->all();

        return route('downloads.guest.show', $order).'?'.http_build_query(['t' => $pairs]);
    }

    public function test_the_emailed_link_redirects_to_a_clean_url_without_the_token(): void
    {
        [$order, $issued] = $this->payForGuestOrder();

        $response = $this->get($this->accessUrl($order, $issued));

        $response->assertRedirect(route('downloads.guest.show', $order));
        $this->assertStringNotContainsString('?', $response->headers->get('Location'));
    }

    public function test_the_verify_page_does_not_expose_order_contents(): void
    {
        [$order, $issued] = $this->payForGuestOrder();

        $this->get($this->accessUrl($order, $issued));
        $response = $this->get(route('downloads.guest.show', $order));

        $response->assertOk();
        $response->assertDontSee('Ready Single');
        $response->assertDontSee((string) $order->total);
        $response->assertSee('Verify Your Purchase');
    }

    public function test_correct_token_and_correct_email_succeeds_and_reveals_items(): void
    {
        [$order, $issued] = $this->payForGuestOrder('guest@example.com');

        $this->get($this->accessUrl($order, $issued));
        $verify = $this->post(route('downloads.guest.verify', $order), ['email' => 'GUEST@EXAMPLE.COM']);
        $verify->assertRedirect(route('downloads.guest.items', $order));

        $items = $this->get(route('downloads.guest.items', $order));
        $items->assertOk();
        $items->assertSee('Ready Single');
        $items->assertSee('Your purchase is verified.');
    }

    public function test_correct_token_and_wrong_email_is_denied_generically(): void
    {
        [$order, $issued] = $this->payForGuestOrder('guest@example.com');

        $this->get($this->accessUrl($order, $issued));
        $verify = $this->post(route('downloads.guest.verify', $order), ['email' => 'someone-else@example.com']);

        $verify->assertRedirect(route('downloads.guest.show', $order));
        $verify->assertSessionHas('guest_verify_error');

        $items = $this->get(route('downloads.guest.items', $order));
        $items->assertRedirect(route('downloads.guest.show', $order));
    }

    public function test_no_token_captured_means_verify_fails_even_with_the_correct_email(): void
    {
        [$order] = $this->payForGuestOrder('guest@example.com');

        // Never visited the emailed link — no tokens in session at all.
        $verify = $this->post(route('downloads.guest.verify', $order), ['email' => 'guest@example.com']);

        $verify->assertRedirect(route('downloads.guest.show', $order));
        $verify->assertSessionHas('guest_verify_error');
    }

    public function test_a_verified_session_for_order_a_cannot_access_order_bs_items(): void
    {
        [$orderA, $issuedA] = $this->payForGuestOrder('guest-a@example.com');
        [$orderB] = $this->payForGuestOrder('guest-b@example.com');

        $this->get($this->accessUrl($orderA, $issuedA));
        $this->post(route('downloads.guest.verify', $orderA), ['email' => 'guest-a@example.com']);

        $response = $this->get(route('downloads.guest.items', $orderB));

        $response->assertRedirect(route('downloads.guest.show', $orderB));
    }

    public function test_order_bs_token_cannot_authorize_a_download_against_order_a(): void
    {
        [$orderA, $issuedA] = $this->payForGuestOrder('guest-a@example.com');
        [$orderB, $issuedB, $singleB] = $this->payForGuestOrder('guest-b@example.com');

        // Verified for Order A, but the track being requested belongs to
        // Order B — Order A's session simply has no token for it.
        $this->get($this->accessUrl($orderA, $issuedA));
        $this->post(route('downloads.guest.verify', $orderA), ['email' => 'guest-a@example.com']);

        $response = $this->get(route('downloads.guest.track', [$orderA, $singleB->track]));

        $response->assertRedirect(route('downloads.guest.items', $orderA));
        $response->assertSessionHas('download_error');
    }

    public function test_a_multi_item_order_exposes_all_purchased_items_after_one_verification(): void
    {
        $albumA = $this->readyAlbum();
        $singleB = $this->readySingle();
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $order = app(CreatePendingOrderAction::class)->handle($albumA, null, 'guest@example.com');
        app(AddToCartAction::class)->handle($order, $singleB);
        $issued = app(MarkOrderPaidAction::class)->handle($order->refresh(), 'pi_multi', 'evt_multi');

        $this->get($this->accessUrl($order->refresh(), $issued));
        $this->post(route('downloads.guest.verify', $order), ['email' => 'guest@example.com']);

        $response = $this->get(route('downloads.guest.items', $order));

        $response->assertOk();
        $response->assertSee($albumA->title);
        $response->assertSee($singleB->title);
    }

    public function test_a_verified_guest_can_download_and_the_existing_download_log_and_counter_apply(): void
    {
        [$order, $issued, $single] = $this->payForGuestOrder('guest@example.com');
        $issued[0]->entitlement->update(['max_downloads' => 1, 'downloads_used' => 0]);

        $this->get($this->accessUrl($order, $issued));
        $this->post(route('downloads.guest.verify', $order), ['email' => 'guest@example.com']);

        $first = $this->get(route('downloads.guest.track', [$order, $single->track]));
        $first->assertOk();
        $first->assertHeader('content-disposition');

        $second = $this->get(route('downloads.guest.track', [$order, $single->track]));
        $second->assertRedirect(route('downloads.guest.items', $order));
        $second->assertSessionHas('download_error');
    }

    public function test_a_revoked_entitlement_denies_the_guest_download(): void
    {
        [$order, $issued, $single] = $this->payForGuestOrder('guest@example.com');
        // revoked_at is deliberately not Fillable (see Entitlement's own
        // #[Fillable] list — revocation is an explicit admin action, never
        // mass-assignable) — set it directly rather than via update().
        $entitlement = $issued[0]->entitlement;
        $entitlement->revoked_at = now();
        $entitlement->save();

        $this->get($this->accessUrl($order, $issued));
        $this->post(route('downloads.guest.verify', $order), ['email' => 'guest@example.com']);

        $response = $this->get(route('downloads.guest.track', [$order, $single->track]));

        $response->assertRedirect(route('downloads.guest.items', $order));
        $response->assertSessionHas('download_error');
    }

    public function test_the_verify_endpoint_is_throttled(): void
    {
        [$order] = $this->payForGuestOrder('guest@example.com');

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('downloads.guest.verify', $order), ['email' => 'wrong@example.com']);
        }

        $response = $this->post(route('downloads.guest.verify', $order), ['email' => 'wrong@example.com']);

        $response->assertStatus(429);
    }
}
