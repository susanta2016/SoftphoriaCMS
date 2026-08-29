<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Phase 4 — /account/orders, the registered purchaser's digital purchase/
 * download library. Distinct from TransactionHistoryTest's payment ledger;
 * scoped entirely through Auth::user()->orders(), same "no route parameter
 * to manipulate" shape as TransactionController.
 */
class OrderHistoryTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.orders'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_with_no_orders_sees_an_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.orders'));

        $response->assertOk();
        $response->assertSee("don't have any purchases", false);
    }

    public function test_the_owner_sees_their_own_paid_order_with_a_working_download_link(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $user = User::factory()->create();
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $response = $this->actingAs($user)->get(route('account.orders'));

        $response->assertOk();
        $response->assertSee('Ready Single');
        $response->assertSee(route('music.tracks.download', $single->track), false);

        $download = $this->actingAs($user)->get(route('music.tracks.download', $single->track));
        $download->assertOk();
        $download->assertHeader('content-disposition');
    }

    public function test_a_user_never_sees_another_users_orders(): void
    {
        $userA = User::factory()->create();
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), $userA, $userA->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_2', 'evt_2');

        $userB = User::factory()->create();

        $response = $this->actingAs($userB)->get(route('account.orders'));

        $response->assertOk();
        $response->assertDontSee('Ready Single');
    }

    public function test_a_pending_unpaid_order_is_not_shown(): void
    {
        $user = User::factory()->create();
        app(CreatePendingOrderAction::class)->handle($this->readySingle(), $user, $user->email);

        $response = $this->actingAs($user)->get(route('account.orders'));

        $response->assertOk();
        $response->assertSee("don't have any purchases", false);
    }

    public function test_the_orders_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.orders'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_orders_page_is_absent_from_the_sitemap(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee(route('account.orders'), false);
    }
}
