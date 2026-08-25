<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Modules\Commerce\Enums\PaymentTransactionStatus;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account subscription page — Pro Membership plan summary plus renewal
 * history. A user has at most one Subscription row ever (HasOne), so past
 * renewals live only as `subscription_invoice_paid`/`_failed`
 * PaymentTransaction rows against it, never as separate Subscription rows.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.subscription'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_free_member_sees_a_no_subscription_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.subscription'));

        $response->assertOk();
        $response->assertSee('Free Member');
        $response->assertDontSee('Renewal History');
    }

    public function test_a_pro_member_sees_their_plan_and_renewal_history(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'price_at_subscription' => 12.34,
            'started_at' => now()->subMonths(2),
            'current_period_end' => now()->addDays(20),
        ]);

        PaymentTransaction::query()->create([
            'subscription_id' => $subscription->id,
            'type' => PaymentTransactionType::SubscriptionInvoicePaid,
            'status' => PaymentTransactionStatus::Succeeded,
            'amount' => 12.34,
            'currency' => 'usd',
            'occurred_at' => now()->subMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('account.subscription'));

        $response->assertOk();
        $response->assertSee('Active');
        $response->assertSee('12.34');
        $response->assertSee('Renewal History');
        $response->assertSee('Succeeded');
    }

    public function test_renewal_history_never_shows_another_users_transactions(): void
    {
        $userA = User::factory()->create();
        $subscriptionA = Subscription::query()->create([
            'user_id' => $userA->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);
        PaymentTransaction::query()->create([
            'subscription_id' => $subscriptionA->id,
            'type' => PaymentTransactionType::SubscriptionInvoicePaid,
            'status' => PaymentTransactionStatus::Succeeded,
            'amount' => 99.99,
            'currency' => 'usd',
            'occurred_at' => now(),
        ]);

        $userB = User::factory()->create();

        $response = $this->actingAs($userB)->get(route('account.subscription'));

        $response->assertOk();
        $response->assertDontSee('99.99');
    }

    public function test_the_subscription_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.subscription'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
