<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §3/§22: "existing Order Item price does not change when Global Pricing
 * changes" — the whole reason order_items.unit_price is a snapshot and not
 * a live read.
 */
class PricingSnapshotTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_changing_global_pricing_does_not_alter_an_existing_orders_price(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('pricing', 'full_album_price', '9.99');

        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');
        $originalItemPrice = (string) $order->items->first()->unit_price;

        $this->assertSame('9.99', $originalItemPrice);

        $settings->set('pricing', 'full_album_price', '19.99');

        $this->assertSame('9.99', (string) $order->items->first()->refresh()->unit_price);
        $this->assertSame('9.99', (string) $order->refresh()->total);
    }
}
