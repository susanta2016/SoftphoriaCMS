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

    /**
     * A genuine concurrent duplicate (Stripe's own documented retry
     * behavior, or the CLI forwarding both the normal and Connect copy of
     * one triggered event) can land between the exists() check and the
     * insert — the exists() check alone doesn't close that race. Simulated
     * here via a `saving` hook that inserts the conflicting row at the
     * exact moment this handler tries to, forcing the real unique-
     * constraint violation this action must swallow rather than let
     * bubble up as an unhandled 500. Because the insert and the conflict
     * both happen inside handle()'s own DB::transaction(), the rollback
     * that follows undoes both rows — same as if the whole request had
     * simply never run — so what actually matters here is that nothing
     * throws and the order is left cleanly unpaid, not a specific
     * surviving row count (see HandleInvoicePaymentSucceededAction's own
     * race test for a case that isn't transaction-wrapped, where the
     * concurrent row does survive).
     */
    public function test_a_concurrent_duplicate_event_id_does_not_crash(): void
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->readyAlbum(), null, 'guest@example.com');

        PaymentTransaction::saving(function (PaymentTransaction $transaction): void {
            if ($transaction->provider_event_id === 'evt_race') {
                PaymentTransaction::query()->insert([
                    'order_id' => $transaction->order_id,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'provider_event_id' => 'evt_race',
                    'provider_reference' => $transaction->provider_reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'occurred_at' => $transaction->occurred_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        try {
            $result = app(MarkOrderPaidAction::class)->handle($order, 'pi_race', 'evt_race');
        } finally {
            PaymentTransaction::flushEventListeners();
        }

        $this->assertSame([], $result);
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
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
