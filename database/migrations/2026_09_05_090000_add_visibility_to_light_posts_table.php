<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the two-state light_posts.is_public boolean with a three-state
 * `visibility` column (public/private/community) — the client's Gratitude
 * Journal requirement for a genuine "For Community" state distinct from a
 * truly private one (Gratitude Journal three-state visibility change,
 * 2026-09-05).
 *
 * Data-preserving backfill, confirmed against the pre-existing, documented
 * behavior of GratitudeJournalFeedController (is_public = false was already
 * the shared-feed-visible state — see that controller's own docblock: "the
 * one and only place a Private entry is ever shown to anyone besides its
 * author") and HomeController::latestGratitudeEntries() (is_public = true
 * was already the homepage-only state):
 *   - is_public = true  -> visibility = 'public'    (unchanged: homepage)
 *   - is_public = false -> visibility = 'community' (unchanged: shared feed)
 * The new 'private' value has no pre-existing rows mapped to it — a genuine
 * new state, only reachable going forward through the account Journal
 * form's new three-option selector.
 *
 * Applies uniformly to every row regardless of `source`: a registration-time
 * Light Post (always is_public = true, never false — see
 * CreatesLightPostOnRegistration) becomes visibility = 'public', which is
 * exactly its existing, unchanged behavior — LightPostController::show()
 * still gates on visibility = public AND source = registration, and a
 * registration post can never become 'community' (that flow never sets it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('light_posts', function (Blueprint $table) {
            $table->string('visibility')->default('public')->after('is_public');
        });

        DB::table('light_posts')->where('is_public', true)->update(['visibility' => 'public']);
        DB::table('light_posts')->where('is_public', false)->update(['visibility' => 'community']);

        Schema::table('light_posts', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'created_at']);
            $table->dropColumn('is_public');
            $table->index(['visibility', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('light_posts', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('source');
        });

        DB::table('light_posts')->where('visibility', 'public')->update(['is_public' => true]);
        DB::table('light_posts')->whereIn('visibility', ['private', 'community'])->update(['is_public' => false]);

        Schema::table('light_posts', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'created_at']);
            $table->dropColumn('visibility');
            $table->index(['is_public', 'created_at']);
        });
    }
};
