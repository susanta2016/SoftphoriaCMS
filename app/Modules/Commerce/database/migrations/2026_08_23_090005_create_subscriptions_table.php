<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: application-owned Pro Membership state, one row per user
 * (user_id unique). status mirrors Stripe's own subscription status
 * vocabulary verbatim (App\Modules\Commerce\Enums\SubscriptionStatus) so
 * webhook handlers map 1:1 without translating. price_at_subscription
 * snapshots Global Pricing's pro_member_monthly_price at signup — the same
 * historical-snapshot principle as order_items.unit_price; Global Pricing
 * changes never alter an existing subscriber's recorded price. See
 * App\Modules\Commerce\Models\Subscription for why membership access is
 * resolved live from `status` rather than materialized as per-track/
 * per-release Entitlement rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('status')->index();
            $table->decimal('price_at_subscription', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
