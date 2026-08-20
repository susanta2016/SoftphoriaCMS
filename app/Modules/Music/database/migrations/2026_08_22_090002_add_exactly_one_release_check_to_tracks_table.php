<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level backstop for "a track belongs to exactly one of an Album
 * or a Single" — the Filament form and Create/UpdateTrackAction already
 * enforce this, but neither protects against a raw Track::create()/insert()
 * from Tinker, a seeder, a queued job, or a future API/importer.
 *
 * MySQL/MariaDB only: this app's production/dev connection is MariaDB
 * 10.11, which fully supports and enforces CHECK constraints (unlike MySQL
 * before 8.0.16, where CHECK was parsed but silently ignored). The test
 * suite runs on SQLite in-memory (phpunit.xml), and SQLite cannot ALTER an
 * existing table to add a CHECK constraint — only CREATE TABLE-time
 * constraints are supported, and retrofitting one means rebuilding the
 * whole table. Laravel's Schema\Blueprint has no cross-driver ->check()
 * helper in this version, so rather than hand-rolling a risky SQLite
 * table-rebuild for a constraint the test suite doesn't need at the SQL
 * layer, this migration is a MariaDB/MySQL-only no-op elsewhere.
 *
 * Non-DB-layer callers are still fully covered on every driver, including
 * SQLite tests, by Track's own model-level guard — see
 * App\Modules\Music\Models\Track::booted() and
 * App\Modules\Music\Exceptions\InvalidTrackReleaseException. That guard
 * fires on every Eloquent save regardless of driver; this constraint is the
 * additional layer that also catches raw SQL bypassing Eloquent entirely.
 */
return new class extends Migration
{
    private const string CONSTRAINT_NAME = 'tracks_exactly_one_release_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE tracks ADD CONSTRAINT '.self::CONSTRAINT_NAME.' '.
            'CHECK ((album_id IS NOT NULL AND single_id IS NULL) OR (album_id IS NULL AND single_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tracks DROP CONSTRAINT '.self::CONSTRAINT_NAME);
    }
};
