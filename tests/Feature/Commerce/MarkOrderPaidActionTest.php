<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §22: "duplicate payment processing is prevented/idempotent" — a webhook
 * retry (the same Stripe event id arriving twice) must not double-charge a
 * second Entitlement or a second ledger row.
 */
class MarkOrderPaidActionTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_marking_an_order_paid_issues_an_entitlement_and_records_a_transaction(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');

        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_123', 'evt_123');

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertNotNull($order->paid_at);
        $this->assertCount(1, $issued);
        $this->assertSame('guest@example.com', $issued[0]->entitlement->purchaser_email);
        $this->assertSame(1, PaymentTransaction::query()->count());
    }

    public function test_replaying_the_same_stripe_event_is_a_no_op(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');

        app(MarkOrderPaidAction::class)->handle($order, 'pi_123', 'evt_dup');
        $secondResult = app(MarkOrderPaidAction::class)->handle($order->refresh(), 'pi_123', 'evt_dup');

        $this->assertSame([], $secondResult);
        $this->assertSame(1, PaymentTransaction::query()->where('provider_event_id', 'evt_dup')->count());
        $this->assertSame(1, $order->items->first()->refresh()->entitlement()->count());
    }

    public function test_no_card_or_payment_credential_data_is_ever_stored(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');
        app(MarkOrderPaidAction::class)->handle($order, 'pi_123', 'evt_123');

        $this->assertFalse(Schema::hasColumn('orders', 'card_number'));
        $this->assertFalse(Schema::hasColumn('payment_transactions', 'card_number'));
        $this->assertFalse(Schema::hasColumn('payment_transactions', 'cvv'));
    }
}
