<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the two-column CHECK constraint from
 * 2026_08_23_090004_add_exactly_one_release_check_to_entitlements_table.php
 * with a three-column version now that track_id exists — see the equivalent
 * order_items migration for full reasoning (identical pattern).
 */
return new class extends Migration
{
    private const string OLD_CONSTRAINT_NAME = 'entitlements_exactly_one_release_check';

    private const string NEW_CONSTRAINT_NAME = 'entitlements_exactly_one_release_check_v2';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE entitlements DROP CONSTRAINT '.self::OLD_CONSTRAINT_NAME);
        DB::statement(
            'ALTER TABLE entitlements ADD CONSTRAINT '.self::NEW_CONSTRAINT_NAME.' '.
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

        DB::statement('ALTER TABLE entitlements DROP CONSTRAINT '.self::NEW_CONSTRAINT_NAME);
        DB::statement(
            'ALTER TABLE entitlements ADD CONSTRAINT '.self::OLD_CONSTRAINT_NAME.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }
};
