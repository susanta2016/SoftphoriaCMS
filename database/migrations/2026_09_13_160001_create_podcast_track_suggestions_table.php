<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated "You May Also Like — Music Tracks" suggestions, attached to
 * PodcastEpisode (the Podcast side's own detail-page entity — mirrors the
 * Music side's music_podcast_suggestions table exactly, just a plain
 * many-to-many since PodcastEpisode is the only "suggestable" type on this
 * side, no polymorphism needed). Manually curated only. sort_order preserves
 * the admin's chosen display order (App\Modules\Podcast\Filament\Support\
 * MusicTrackSuggestionsField writes it from the multi-select's array order
 * on save). Feature-flagged at read time via config('features.
 * music_track_suggestions_enabled') — rows are never deleted just because
 * that flag is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('podcast_track_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['podcast_episode_id', 'track_id']);
            $table->index(['podcast_episode_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_track_suggestions');
    }
};
