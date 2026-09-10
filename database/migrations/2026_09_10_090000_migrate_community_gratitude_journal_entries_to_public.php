<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client-approved simplification (2026-09-10): Gratitude Journal visibility
 * drops from three states to two — Public/Private only, "For Community" is
 * removed entirely. Every existing Journal entry (source = journal) with
 * visibility = 'community' becomes visibility = 'public', per the client's
 * explicit, confirmed mapping: those entries were already shown to the
 * whole member base on the shared feed, and will now also appear on the
 * homepage carousel — an intentional, one-way business-rule change, not a
 * lossless rename.
 *
 * Scoped to source = journal only: a registration-time Light Post
 * (CreatesLightPostOnRegistration) is always created as 'public' already and
 * never becomes 'community', so this WHERE clause is a defensive no-op for
 * that source, not a behavior change.
 *
 * No schema/column-type change — light_posts.visibility remains the plain
 * string column added by 2026_09_05_090000_add_visibility_to_light_posts_table.
 * This migration only rewrites data; App\Enums\GratitudeJournalVisibility's
 * Community case is removed in the same deploy so no row can read back as an
 * unrecognized enum value.
 *
 * down() intentionally does not restore 'community' — the client explicitly
 * confirmed this is a one-way business-rule change, and reconstructing which
 * 'public' rows were originally 'community' after the fact is not possible
 * (nor desired) once this ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('light_posts')
            ->where('source', 'journal')
            ->where('visibility', 'community')
            ->update(['visibility' => 'public']);
    }

    public function down(): void
    {
        // Deliberately no-op — see docblock above.
    }
};
