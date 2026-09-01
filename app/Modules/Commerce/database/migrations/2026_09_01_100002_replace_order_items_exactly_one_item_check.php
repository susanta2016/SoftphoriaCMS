<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the two-column CHECK constraint from
 * 2026_08_23_090002_add_exactly_one_item_check_to_order_items_table.php with
 * a three-column version now that track_id exists — an order item must
 * reference exactly one of an Album, a Single, or a Track. MySQL/MariaDB-only,
 * same reasoning as the constraint it replaces (SQLite, used by the test
 * suite, cannot ALTER a table to add a CHECK constraint — the model-level
 * BelongsToExactlyOneOf guard covers every driver).
 */
return new class extends Migration
{
    private const string OLD_CONSTRAINT_NAME = 'order_items_exactly_one_item_check';

    private const string NEW_CONSTRAINT_NAME = 'order_items_exactly_one_item_check_v2';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT '.self::OLD_CONSTRAINT_NAME);
        DB::statement(
            'ALTER TABLE order_items ADD CONSTRAINT '.self::NEW_CONSTRAINT_NAME.' '.
            'CHECK ('.
            '(album_id IS NOT NULL AND single_id IS NULL AND track_id IS NULL) OR '.
            '(album_id IS NULL AND single_id IS NOT NULL AND track_id IS NULL) OR '.
            '(album_id IS NULL AND single_id IS NULL AND track_id IS NOT NULL)'.
            ')'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT '.self::NEW_CONSTRAINT_NAME);
        DB::statement(
            'ALTER TABLE order_items ADD CONSTRAINT '.self::OLD_CONSTRAINT_NAME.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }
};
