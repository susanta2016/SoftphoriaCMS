<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Core-authored Phase-1 schema (2026_08_10_100704/100705) keyed
 * podcast_categories/podcast_tags to the show (podcast_id) — Database
 * Specification §5 was ambiguous about which level "classification pivots"
 * meant. The approved Podcast List/Episode designs (docs/Reference UI/
 * Frontend) settled it: topics vary per episode (the list's "Topic" filter,
 * the episode detail's "Key Themes"), not per show. Both tables are still
 * empty (no admin UI ever wrote to them), so this repoints them to
 * podcast_episode_id rather than leaving unused show-level tables beside
 * new episode-level ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('podcast_tags');
        Schema::dropIfExists('podcast_categories');

        Schema::create('podcast_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['podcast_episode_id', 'category_id']);
        });

        Schema::create('podcast_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['podcast_episode_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_tags');
        Schema::dropIfExists('podcast_categories');

        Schema::create('podcast_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['podcast_id', 'category_id']);
        });

        Schema::create('podcast_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['podcast_id', 'tag_id']);
        });
    }
};
