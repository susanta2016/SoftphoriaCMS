<?php

namespace Tests\Feature\Music;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Support\CartSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Music cart — session-held only (no DB row until checkout, see
 * CartSession's docblock), no quantity, no duplicate lines.
 */
class CartControllerTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_a_guest_can_add_a_ready_album_to_the_cart(): void
    {
        $album = $this->readyAlbum();

        $response = $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);

        $response->assertRedirect();
        $response->assertSessionHas('cart_added');
        $this->assertSame(1, CartSession::count());
        $this->assertTrue(CartSession::has('album', $album->getKey()));
    }

    public function test_the_listening_page_shows_a_checkout_confirmation_modal_after_adding_to_cart(): void
    {
        $single = $this->readySingle();

        $this->from(route('music.singles.show', $single))
            ->post(route('cart.add'), ['type' => 'single', 'slug' => $single->slug]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('added to your cart', false);
        $response->assertSee('Go to checkout now?');
        $response->assertSee(route('checkout.show'), false);
    }

    public function test_adding_the_same_item_twice_does_not_create_a_duplicate_line(): void
    {
        $album = $this->readyAlbum();

        $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);
        $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);

        $this->assertSame(1, CartSession::count());
    }

    public function test_an_album_and_a_single_can_both_be_in_the_cart_at_once(): void
    {
        $album = $this->readyAlbum();
        $single = $this->readySingle();

        $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);
        $this->post(route('cart.add'), ['type' => 'single', 'slug' => $single->slug]);

        $this->assertSame(2, CartSession::count());
    }

    public function test_a_release_that_is_not_purchase_ready_cannot_be_added(): void
    {
        $album = $this->album(['title' => 'Not Ready', 'status' => ReleaseStatus::Published]);

        $response = $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);

        $response->assertSessionHas('cart_error');
        $this->assertSame(0, CartSession::count());
    }

    public function test_a_draft_release_cannot_be_added(): void
    {
        $album = $this->album(['title' => 'Draft', 'slug' => 'draft-album', 'status' => ReleaseStatus::Draft]);

        $response = $this->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);

        $response->assertSessionHas('cart_error');
        $this->assertSame(0, CartSession::count());
    }

    public function test_a_pro_member_is_told_the_release_is_already_included_instead_of_adding_it(): void
    {
        $user = $this->admin();
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);
        $album = $this->readyAlbum();

        $response = $this->actingAs($user)->post(route('cart.add'), ['type' => 'album', 'slug' => $album->slug]);

        $response->assertSessionHas('cart_notice');
        $this->assertSame(0, CartSession::count());
    }

    public function test_a_user_who_already_owns_the_release_is_told_instead_of_adding_it(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $response = $this->actingAs($user)->post(route('cart.add'), ['type' => 'single', 'slug' => $single->slug]);

        $response->assertSessionHas('cart_notice');
        $this->assertSame(0, CartSession::count());
    }

    public function test_removing_an_item_takes_it_out_of_the_cart(): void
    {
        $album = $this->readyAlbum();
        CartSession::add('album', $album->getKey());

        $response = $this->post(route('cart.remove'), ['type' => 'album', 'id' => $album->getKey()]);

        $response->assertRedirect(route('cart.show'));
        $this->assertSame(0, CartSession::count());
    }

    public function test_the_cart_page_lists_added_items_with_price_and_no_quantity_controls(): void
    {
        $album = $this->readyAlbum();
        CartSession::add('album', $album->getKey());

        $response = $this->get(route('cart.show'));

        $response->assertOk();
        $response->assertSee($album->title);
        $response->assertSee('9.99');
        $response->assertDontSee('name="quantity"', false);
    }

    public function test_the_empty_cart_page_does_not_error(): void
    {
        $response = $this->get(route('cart.show'));

        $response->assertOk();
    }

    public function test_a_line_for_a_release_that_became_unpublished_after_adding_is_dropped_silently(): void
    {
        $album = $this->readyAlbum();
        CartSession::add('album', $album->getKey());

        $album->update(['status' => ReleaseStatus::Draft]);

        $response = $this->get(route('cart.show'));

        $response->assertOk();
        $response->assertDontSee($album->title);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }
}
