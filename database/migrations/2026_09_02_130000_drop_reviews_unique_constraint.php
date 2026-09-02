<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed reversal (2026-09-02): a member may now leave multiple
 * Light Post/comments on the same content item over time — the old
 * "one review per (reviewable, user)" rule made sense for a star rating
 * (you rate something once), but not for a comment feed.
 *
 * Drops ONLY the unique index `reviews_reviewable_type_reviewable_id_
 * user_id_unique` (created by 2026_09_01_090002_create_reviews_table.php's
 * `$table->unique(['reviewable_type', 'reviewable_id', 'user_id'])`) — a
 * pure DDL constraint removal. No column, row, or data is touched; the 3
 * existing legacy-rated rows are unaffected. The two indexes that actually
 * serve query performance stay in place untouched:
 * `reviews_reviewable_type_reviewable_id_index` (from `$table->morphs()`)
 * and `reviews_reviewable_type_reviewable_id_status_index` (the one the
 * public "approved comments for this item" query uses).
 *
 * App\Actions\Review\SubmitReviewAction switches from `updateOrCreate()`
 * (which relied on this exact unique key) to a plain `create()` — every
 * submission is now its own new Review row, independently moderated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_reviewable_type_reviewable_id_user_id_unique');
        });
    }

    /**
     * Only safe to run if no user has submitted more than one comment on
     * the same item since up() ran — documented rather than guarded,
     * matching this repo's existing convention for a down() that isn't
     * unconditionally safe (see 2026_09_02_120000_make_reviews_rating_nullable.php).
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['reviewable_type', 'reviewable_id', 'user_id']);
        });
    }
};
