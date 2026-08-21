<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Track's `video_embed_url` is an external reference only (YouTube/Vimeo),
 * the same role MusicStreamingLink plays — never a private/downloadable
 * asset. This adds the video counterpart to `audio_media_id`: an optional,
 * privately-stored (MediaCategory::Video → the `local` disk, never
 * `public`) uploaded video file, giving Admin a real preview/playback
 * source distinct from the external embed. Nullable — most tracks will
 * have neither, some may have only `video_embed_url`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->foreignId('video_media_id')->nullable()->after('audio_media_id')
                ->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_media_id');
        });
    }
};
