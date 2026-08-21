<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level backstop: every payment_transactions row must relate to at
 * least one of an order or a subscription (a transaction relating to
 * neither has no reason to exist). MySQL/MariaDB-only — see the equivalent
 * order_items/entitlements CHECK migrations for full reasoning.
 */
return new class extends Migration
{
    private const string CONSTRAINT_NAME = 'payment_transactions_order_or_subscription_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE payment_transactions ADD CONSTRAINT '.self::CONSTRAINT_NAME.' '.
            'CHECK (order_id IS NOT NULL OR subscription_id IS NOT NULL)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE payment_transactions DROP CONSTRAINT '.self::CONSTRAINT_NAME);
    }
};
