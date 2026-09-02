<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed reversal (2026-09-02): the public review form no longer
 * collects a star rating (replaced by a plain text comment + a separate 🙌
 * reaction — see App\Models\Reaction). This migration only drops the
 * NOT NULL constraint on `reviews.rating` — a pure ALTER, non-destructive.
 * Every existing row's rating value is preserved untouched; only new
 * inserts are now allowed to omit it (App\Actions\Review\SubmitReviewAction
 * always writes `rating = null` for a submission made through the new
 * comment-only public form). The column itself, and all legacy data, are
 * deliberately kept — never dropped — so existing rated reviews remain
 * intact and inspectable in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->change();
        });
    }

    /**
     * Only safe to run if no row has a null rating yet (i.e. immediately
     * after up(), before any new comment-only submission has been made) —
     * this is documented rather than guarded, matching this repo's existing
     * convention for migrations whose down() isn't unconditionally safe.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable(false)->change();
        });
    }
};
