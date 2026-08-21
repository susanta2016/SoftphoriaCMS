<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: append-only ledger of monetary Stripe events — deliberately
 * separate from orders.status/subscriptions.status (each a current-state
 * summary), the same current-state-plus-event-log split Stripe itself uses.
 * provider_event_id is what makes webhook processing idempotent (a handler
 * no-ops if the Stripe event id has already been recorded) and is also the
 * refund/failed-renewal audit trail neither orders nor subscriptions is
 * meant to carry directly. Never stores card/CVV/raw payment credentials —
 * only Stripe's own provider-side references. See
 * App\Modules\Commerce\Models\PaymentTransaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->restrictOnDelete();
            $table->string('type');
            $table->string('status');
            $table->string('provider')->default('stripe');
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('provider_reference')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
