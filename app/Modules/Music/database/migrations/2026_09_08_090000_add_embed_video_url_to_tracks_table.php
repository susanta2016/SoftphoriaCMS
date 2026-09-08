<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second, independent video-URL field for tracks — distinct from the
 * pre-existing tracks.video_embed_url (Song Story section, rendered as the
 * small "Watch video" icon beside the Song Story heading; left untouched).
 * This one mirrors albums.embed_video_url exactly: a YouTube-only URL that
 * drives a "Watch Video" button placed among the primary action buttons,
 * directly before the Share buttons, on the track/single listening page —
 * client-requested restoration of that button at the track level, 2026-09-08.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('embed_video_url')->nullable()->after('audio_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('embed_video_url');
        });
    }
};
