<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Commerce\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Modules\Commerce\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_non_admin_cannot_access_subscriptions(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/subscriptions');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_subscription_list_and_a_single_subscription(): void
    {
        $subscription = Subscription::query()->create([
            'user_id' => $this->admin()->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListSubscriptions::class)
            ->assertCanSeeTableRecords([$subscription]);

        Livewire::actingAs($this->admin())
            ->test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
            ->assertOk();
    }

    /**
     * §4/§12: no Edit route exists for Subscription at all — the deliberate
     * answer to "do not allow the Admin UI to manually put the application
     * into a state that contradicts Stripe."
     */
    public function test_subscriptions_have_no_edit_route(): void
    {
        $this->assertArrayNotHasKey(
            'edit',
            (new ReflectionClass(SubscriptionResource::class))->getMethod('getPages')->invoke(null),
        );
    }
}
