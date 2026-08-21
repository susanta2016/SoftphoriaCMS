<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level backstop for "an entitlement references exactly one of an
 * Album or a Single" — see the equivalent migration for order_items for full
 * reasoning (identical pattern, MySQL/MariaDB-only).
 */
return new class extends Migration
{
    private const string CONSTRAINT_NAME = 'entitlements_exactly_one_release_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE entitlements ADD CONSTRAINT '.self::CONSTRAINT_NAME.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE entitlements DROP CONSTRAINT '.self::CONSTRAINT_NAME);
    }
};
