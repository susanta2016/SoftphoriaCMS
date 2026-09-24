<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relaxes 2026_08_22_090002's "exactly one of album_id/single_id" CHECK to
 * "at least one" — the same song can be released as a Single and also
 * appear on an Album. MySQL/MariaDB only, for the same reason as the
 * original constraint (see that migration's docblock); Track::booted() is
 * the model-level guard on every driver.
 */
return new class extends Migration
{
    private const string OLD_CONSTRAINT = 'tracks_exactly_one_release_check';

    private const string NEW_CONSTRAINT = 'tracks_at_least_one_release_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tracks DROP CONSTRAINT '.self::OLD_CONSTRAINT);
        DB::statement(
            'ALTER TABLE tracks ADD CONSTRAINT '.self::NEW_CONSTRAINT.' '.
            'CHECK (album_id IS NOT NULL OR single_id IS NOT NULL)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tracks DROP CONSTRAINT '.self::NEW_CONSTRAINT);
        DB::statement(
            'ALTER TABLE tracks ADD CONSTRAINT '.self::OLD_CONSTRAINT.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }
};
