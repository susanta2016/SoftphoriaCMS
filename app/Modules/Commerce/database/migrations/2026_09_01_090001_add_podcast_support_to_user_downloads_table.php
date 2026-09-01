<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing download audit trail (App\Modules\Commerce\Models\
 * DownloadLog, table `user_downloads`) to also cover free, registered-user
 * Podcast Episode downloads, rather than standing up a second, parallel
 * download-history mechanism for Podcast. `track_id` was NOT NULL (every row
 * used to be a Track download); it must become nullable so a Podcast-episode
 * row can leave it null. `podcast_episode_id` mirrors `track_id`'s own
 * definition (nullable FK, restrictOnDelete, indexed with user_id) — exactly
 * one of the two is ever set per row, enforced by the writer
 * (AuthorizePodcastEpisodeDownloadAction / AuthorizeTrackDownloadAction),
 * not by a DB-level check constraint.
 *
 * The original ADMIN-008 migration could safely drop+recreate this table
 * because it was confirmed unused at the time; AuthorizeTrackDownloadAction
 * now actively writes real download history to it, so that approach would
 * destroy production data here. Relaxing track_id's NOT NULL constraint via
 * Blueprint::change() needs doctrine/dbal — added as a new dependency
 * specifically for this, since a raw ALTER ... MODIFY is MySQL-only syntax
 * and this app's test suite runs on SQLite (phpunit.xml), which has no
 * equivalent statement; DBAL is what lets Laravel translate this one
 * portably across both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_downloads', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->change();
        });

        Schema::table('user_downloads', function (Blueprint $table) {
            $table->foreignId('podcast_episode_id')->nullable()->after('track_id')
                ->constrained('podcast_episodes')->restrictOnDelete();

            $table->index(['user_id', 'podcast_episode_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_downloads', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'podcast_episode_id']);
            $table->dropConstrainedForeignId('podcast_episode_id');
        });

        Schema::table('user_downloads', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable(false)->change();
        });
    }
};
