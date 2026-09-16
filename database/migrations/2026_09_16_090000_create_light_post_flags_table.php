<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 🚩 "report this entry" action on a Gratitude Journal shared-feed
 * entry (App\Models\LightPost) — same shape as
 * database/migrations/2026_09_15_120000_create_review_flags_table.php for
 * comments, but LightPost has no separate Review/comment row of its own to
 * flag (Gratitude Journal is reaction-only, per config/features.php), so
 * the entry itself is the flaggable content. Its own table rather than a
 * column on `light_posts` for the same reason review_flags is separate:
 * more than one member can flag the same entry. No `reason` column — a
 * single 🚩 click, not a report form. The unique index doubles as the
 * action's own backstop against a double-click/retry race (see
 * App\Actions\GratitudeJournal\FlagGratitudeJournalEntryAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('light_post_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('light_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['light_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('light_post_flags');
    }
};
