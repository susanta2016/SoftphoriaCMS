<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Core-authored Phase-1 `tracks` table (2026_08_10_100603) only carried
 * ordering/duration. The approved Music listening-page design
 * (docs/Reference UI/Frontend/Music_listening_v1.1.0.png) shows a per-song
 * "Details" panel (Written by, Produced by, ISRC — Length already existed as
 * duration_seconds) plus an optional embedded video and, per the Master
 * Scope Specification §8.1/§10.1, a downloadable audio asset. These are
 * song-level facts (a multi-track album's songs can have different writers/
 * producers), so they belong on tracks, not on albums/singles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('written_by')->nullable()->after('duration_seconds');
            $table->string('produced_by')->nullable()->after('written_by');
            $table->string('isrc')->nullable()->after('produced_by');
            $table->string('video_embed_url')->nullable()->after('isrc');
            $table->foreignId('audio_media_id')->nullable()->after('video_embed_url')
                ->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audio_media_id');
            $table->dropColumn(['written_by', 'produced_by', 'isrc', 'video_embed_url']);
        });
    }
};
