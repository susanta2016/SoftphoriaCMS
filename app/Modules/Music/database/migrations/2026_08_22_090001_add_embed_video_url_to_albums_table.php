<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approved Music requirements list "Embedded Music Videos (YouTube/
 * Vimeo)" as an Album-level field, distinct from a Track's own
 * video_embed_url (a per-song video). This is release-level content — e.g.
 * an official album trailer — not tied to any one track, so it lives on
 * albums, not tracks. Plain URL column, same shape as tracks.video_embed_url
 * — native video hosting stays out of scope per the approved requirements.
 * Not added to singles: a Single's one song already has its own
 * video_embed_url on its Track.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->string('embed_video_url')->nullable()->after('cover_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn('embed_video_url');
        });
    }
};
