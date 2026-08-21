<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Exceptions\PurchaseNotReadyException;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §2/§7/§9/§22: guest order has no required user_id, guest email is
 * retained, registered purchase attaches user_id, price is resolved from
 * Global Pricing at creation time, and an unpublished/incomplete release
 * cannot be ordered.
 */
class CreatePendingOrderActionTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_guest_can_create_an_order_with_no_user_id(): void
    {
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com', 'Guest Person');

        $this->assertNull($order->user_id);
        $this->assertSame('guest@example.com', $order->purchaser_email);
        $this->assertSame('Guest Person', $order->purchaser_name);
        $this->assertTrue($order->isGuest());
    }

    public function test_registered_purchase_attaches_user_id(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();

        $order = app(CreatePendingOrderAction::class)->handle($album, $user, $user->email);

        $this->assertSame($user->getKey(), $order->user_id);
        $this->assertSame($user->email, $order->purchaser_email);
        $this->assertFalse($order->isGuest());
    }

    public function test_single_order_resolves_the_current_global_per_song_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'music_per_song_price', '2.99');

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        $this->assertSame('2.99', (string) $order->total);
        $this->assertSame('2.99', (string) $order->items->first()->unit_price);
    }

    public function test_album_order_resolves_the_current_global_full_album_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'full_album_price', '12.99');

        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');

        $this->assertSame('12.99', (string) $order->total);
    }

    public function test_order_item_snapshots_the_title(): void
    {
        $album = $this->readyAlbum();

        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');

        $this->assertSame('Ready Album', $order->items->first()->item_title);

        $album->update(['title' => 'Renamed Album']);

        $this->assertSame('Ready Album', $order->items->first()->refresh()->item_title);
    }

    public function test_unpublished_album_cannot_be_ordered(): void
    {
        $album = $this->readyAlbum();
        $album->update(['status' => ReleaseStatus::Draft]);

        $this->expectException(PurchaseNotReadyException::class);

        app(CreatePendingOrderAction::class)->handle($album->refresh(), null, 'guest@example.com');
    }

    public function test_unpublished_single_cannot_be_ordered(): void
    {
        $single = $this->readySingle();
        $single->update(['status' => ReleaseStatus::Draft]);

        $this->expectException(PurchaseNotReadyException::class);

        app(CreatePendingOrderAction::class)->handle($single->refresh(), null, 'guest@example.com');
    }
}
