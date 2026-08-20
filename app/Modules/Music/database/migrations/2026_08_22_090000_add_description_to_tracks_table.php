<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short, per-song "about" blurb for the listening page — distinct from
 * song_stories.content (the detailed background story). Both are
 * track-level per the Master Scope Specification's Listening Page fields
 * ("Song description" and "Song story" are listed separately), and both
 * stay on Track, never duplicated onto albums/singles, since a multi-track
 * album's songs can each have their own blurb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
