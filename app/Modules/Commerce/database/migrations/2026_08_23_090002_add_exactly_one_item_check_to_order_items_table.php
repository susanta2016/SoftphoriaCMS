<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level backstop for "an order item references exactly one of an
 * Album or a Single" — mirrors Music's
 * 2026_08_22_090002_add_exactly_one_release_check_to_tracks_table.php
 * exactly, including the MySQL/MariaDB-only scoping (SQLite, used by the
 * test suite, cannot ALTER a table to add a CHECK constraint). Non-DB-layer
 * callers are already covered on every driver by OrderItem's own model-level
 * guard — see App\Shared\Concerns\BelongsToExactlyOneOf.
 */
return new class extends Migration
{
    private const string CONSTRAINT_NAME = 'order_items_exactly_one_item_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE order_items ADD CONSTRAINT '.self::CONSTRAINT_NAME.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT '.self::CONSTRAINT_NAME);
    }
};
