<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Filament\Resources\Entitlements\EntitlementResource;
use App\Modules\Commerce\Filament\Resources\Entitlements\Pages\ListEntitlements;
use App\Modules\Commerce\Filament\Resources\Entitlements\Pages\ViewEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

class EntitlementResourceTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_non_admin_cannot_access_entitlements(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/entitlements');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_entitlement_list_and_revoke_one(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $issued[0]->entitlement;

        Livewire::actingAs($this->admin())
            ->test(ListEntitlements::class)
            ->assertCanSeeTableRecords([$entitlement]);

        Livewire::actingAs($this->admin())
            ->test(ViewEntitlement::class, ['record' => $entitlement->getRouteKey()])
            ->assertOk()
            ->callAction(EntitlementResource::revokeAction()->getName(), data: ['reason' => 'Test'])
            ->assertNotified();

        $this->assertNotNull($entitlement->refresh()->revoked_at);
    }
}
