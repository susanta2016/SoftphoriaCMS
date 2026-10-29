<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the existing download audit trail (DownloadLog, table
 * `user_downloads`) also record full-album ZIP downloads rather than
 * standing up a second history table — same reasoning as the earlier
 * podcast_episode_id extension. A ZIP row sets album_id and leaves
 * track_id/podcast_episode_id null; exactly one of the three is ever set
 * per row, enforced by the writers (AuthorizeTrackDownloadAction,
 * AuthorizeAlbumZipDownloadAction, AuthorizePodcastEpisodeDownloadAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_downloads', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('track_id')->constrained('albums')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_downloads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('album_id');
        });
    }
};
