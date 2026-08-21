<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PodcastEpisode's `embed_url` is an external streaming reference only
 * (Spotify/Apple Podcasts/the host's own player) — the same role
 * MusicStreamingLink plays for Music, never a downloadable/private asset.
 * This adds the equivalent of Track's own `audio_media_id`: an optional,
 * privately-stored (MediaCategory::Audio → the `local` disk, never
 * `public`) uploaded audio file for episodes hosted directly, giving Admin
 * a real preview/playback source distinct from the external embed. Nullable
 * — an episode may still rely on `embed_url` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->foreignId('audio_media_id')->nullable()->after('embed_url')
                ->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audio_media_id');
        });
    }
};
