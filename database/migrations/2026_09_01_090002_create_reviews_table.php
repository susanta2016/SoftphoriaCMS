<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single, generic reviews/ratings table — deliberately not Podcast-specific
 * (this migration lives in the root database/migrations, alongside Page/
 * Media/Category, not inside app/Modules/Podcast) so Music and Inspirational
 * Resources can adopt the exact same App\Models\Review/Actions/Filament
 * resource later via the polymorphic reviewable_type/reviewable_id pair,
 * with no schema change of their own. Podcast is simply the first consumer,
 * via PodcastEpisode::reviews(). One row per (reviewable, user) — the unique
 * index below is both "one active review per user per item" and the
 * server-side backstop against a double-click/retry creating duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->unique(['reviewable_type', 'reviewable_id', 'user_id']);
            $table->index(['reviewable_type', 'reviewable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
