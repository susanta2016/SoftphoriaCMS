<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated "You May Also Like — Podcast Episodes" suggestions, attached
 * to whichever Music release entity actually owns the existing "You may also
 * like" section (music/listening.blade.php) — an Album or a Single, never a
 * Track (a Track's own page borrows its parent Album's related section
 * already; see MusicController::relatedReleases()). suggestable_type/
 * suggestable_id is a polymorphic pivot (App\Modules\Music\Models\Album|
 * Single) rather than two separate near-identical tables — same
 * {name}able_type/{name}able_id convention as reviews/reactions.
 *
 * Manually curated only — never automatic/algorithmic. sort_order preserves
 * the admin's chosen display order (App\Modules\Music\Filament\Support\
 * PodcastSuggestionsField writes it from the multi-select's array order on
 * save). Feature-flagged at read time via config('features.
 * podcast_suggestions_enabled') — this table's rows are never deleted just
 * because that flag is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_podcast_suggestions', function (Blueprint $table) {
            $table->id();
            $table->morphs('suggestable');
            $table->foreignId('podcast_episode_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['suggestable_type', 'suggestable_id', 'podcast_episode_id'], 'music_podcast_suggestions_unique');
            $table->index(['suggestable_type', 'suggestable_id', 'sort_order'], 'music_podcast_suggestions_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_podcast_suggestions');
    }
};
