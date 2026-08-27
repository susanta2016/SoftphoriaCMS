<?php

namespace Tests\Feature\Site;

use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared site header (resources/views/components/site/header.blade.php)
 * rendered via the public Home page — an active Pro Member never has
 * anything left to buy (see MusicController::purchaseState's 'included'
 * state), so their cart icon is hidden rather than pointing at an
 * empty/irrelevant page; and a signed-in user always gets a way to log out
 * from the header itself, not only from deep inside /account.
 */
class HeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_cart_icon_and_no_logout_control(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('cart.show'), false);
        $response->assertDontSee(route('logout'), false);
    }

    public function test_a_logged_in_non_subscriber_sees_the_cart_icon_and_a_logout_control(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('cart.show'), false);
        $response->assertSee(route('logout'), false);
    }

    public function test_an_active_pro_member_does_not_see_the_cart_icon(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertDontSee(route('cart.show'), false);
        $response->assertSee(route('logout'), false);
    }
}
