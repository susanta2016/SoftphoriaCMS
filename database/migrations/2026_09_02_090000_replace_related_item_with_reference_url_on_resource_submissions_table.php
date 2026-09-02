<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed replacement: the "Related Album"/"Related Song" pickers
 * on the public Inspirational Resources form are dropped in favor of a
 * free-text "Reference Website URL" field — a submitter can point to any
 * outside source (not just an in-catalogue Album/Track), which the old
 * pickers couldn't express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropForeign(['related_album_id']);
            $table->dropForeign(['related_track_id']);
            $table->dropColumn(['related_album_id', 'related_track_id']);
            $table->string('reference_url')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropColumn('reference_url');
            $table->foreignId('related_album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->foreignId('related_track_id')->nullable()->constrained('tracks')->nullOnDelete();
        });
    }
};
