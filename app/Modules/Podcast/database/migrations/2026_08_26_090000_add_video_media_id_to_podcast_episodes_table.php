<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The video counterpart to podcast_episodes.audio_media_id (see that
 * column's own migration) — `embed_url` stays the external-only streaming
 * reference; this is an optional, privately-stored (MediaCategory::Video →
 * the `local` disk, never `public`) uploaded video file for episodes that
 * have one, giving Admin a real preview/playback source distinct from the
 * external embed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->foreignId('video_media_id')->nullable()->after('audio_media_id')
                ->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_media_id');
        });
    }
};
