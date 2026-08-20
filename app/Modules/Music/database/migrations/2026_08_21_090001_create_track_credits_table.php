<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approved Music listening-page design's "Credits" panel — a small,
 * ordered list of role/name pairs per song (e.g. "Vocals, Lyrics,
 * Composition" -> "IAWARII"). No spec entity covers this, so it's a new
 * module-owned table rather than columns bolted onto tracks, matching how
 * podcast_links models a per-episode ordered list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_credits');
    }
};
