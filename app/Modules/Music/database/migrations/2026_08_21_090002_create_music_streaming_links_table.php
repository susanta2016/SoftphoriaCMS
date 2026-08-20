<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Streaming provider buttons (Spotify/Apple Music/YouTube/SoundCloud) shown
 * on both the approved Music catalogue page's Featured Release panel and the
 * listening page — release-level, not per-track (Master Scope Specification
 * §8.1: "Single: title, artwork, description and streaming links"). Uses the
 * same dual-nullable-FK shape Core already chose for music_categories/
 * music_tags rather than two separate album_links/single_links tables, so
 * exactly one convention covers "belongs to either an Album or a Single"
 * throughout this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_streaming_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->cascadeOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->cascadeOnDelete();
            $table->string('provider');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('album_id');
            $table->index('single_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_streaming_links');
    }
};
