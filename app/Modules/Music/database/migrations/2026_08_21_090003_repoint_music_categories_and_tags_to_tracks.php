<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Core-authored Phase-1 schema (2026_08_10_100606/100607) keyed
 * music_categories/music_tags to the release (album_id/single_id). The
 * approved Music listening-page design (docs/Reference UI/Frontend/
 * Music_listening_v1.1.0.png) shows Genre ("Inspirational · Acoustic ·
 * Ambient") inside the per-song Details panel, not on the album/catalogue
 * pages — the same "classification varies per child content item, not per
 * parent" pattern the Podcast module already resolved by repointing
 * podcast_categories/podcast_tags to podcast_episode_id (see
 * app/Modules/Podcast/database/migrations/2026_08_20_120000_...). A
 * multi-track album can plausibly mix genres per song, which the old
 * release-level pivot couldn't express at all. Both tables are still empty
 * (no admin UI ever wrote to them — the Music module did not exist before
 * this migration), so this repoints them to track_id rather than leaving
 * unused release-level tables beside new track-level ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('music_tags');
        Schema::dropIfExists('music_categories');

        Schema::create('music_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['track_id', 'category_id']);
        });

        Schema::create('music_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['track_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_tags');
        Schema::dropIfExists('music_categories');

        Schema::create('music_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->cascadeOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('album_id');
            $table->index('single_id');
        });

        Schema::create('music_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->cascadeOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('album_id');
            $table->index('single_id');
        });
    }
};
