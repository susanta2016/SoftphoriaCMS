<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Account transaction history — the user's combined ledger of one-off
 * Single/Album purchases (via their Orders) and Pro Membership renewals (via
 * their Subscription), scoped entirely through Auth::user()'s own
 * ownership since there's no route parameter to attempt another user's data
 * with.
 */
class TransactionHistoryTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.transactions'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_with_no_transactions_sees_an_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.transactions'));

        $response->assertOk();
        $response->assertSee("don't have any transactions", false);
    }

    public function test_the_owner_sees_their_own_order_purchase_and_subscription_renewal(): void
    {
        $user = User::factory()->create();

        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_test_1', 'evt_test_1');

        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);
        PaymentTransaction::query()->create([
            'subscription_id' => $subscription->id,
            'type' => PaymentTransactionType::SubscriptionInvoicePaid,
            'status' => PaymentTransactionStatus::Succeeded,
            'amount' => 12.34,
            'currency' => 'usd',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('account.transactions'));

        $response->assertOk();
        $response->assertSee('Ready Single');
        $response->assertSee('Pro Membership');
    }

    public function test_a_user_never_sees_another_users_transactions(): void
    {
        $userA = User::factory()->create();
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), $userA, $userA->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_test_2', 'evt_test_2');

        $userB = User::factory()->create();

        $response = $this->actingAs($userB)->get(route('account.transactions'));

        $response->assertOk();
        $response->assertSee("don't have any transactions", false);
        $response->assertDontSee('Ready Single');
    }

    public function test_the_transactions_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.transactions'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
