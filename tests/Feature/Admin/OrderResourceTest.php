<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Filament\Resources\Orders\OrderResource;
use App\Modules\Commerce\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Commerce\Filament\Resources\Orders\Pages\ViewOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_non_admin_cannot_access_orders(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/orders');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_order_list(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');

        Livewire::actingAs($this->admin())
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_admin_can_view_an_order_and_revoke_access(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->callAction(OrderResource::revokeAccessAction()->getName(), data: ['reason' => 'Test revoke'])
            ->assertNotified();

        $this->assertNotNull($issued[0]->entitlement->refresh()->revoked_at);
    }
}
