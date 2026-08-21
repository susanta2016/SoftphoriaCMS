<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: one row per purchase attempt (Single or Album), guest or
 * registered. purchaser_email is always set — for a registered purchase it's
 * snapshotted from the User at creation time, so every order is searchable/
 * displayable by email without joining to `users`, and guest purchases never
 * require a User row at all (user_id stays null). See
 * App\Modules\Commerce\Models\Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchaser_email')->index();
            $table->string('purchaser_name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->char('currency', 3)->default('usd');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('payment_provider')->default('stripe');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
