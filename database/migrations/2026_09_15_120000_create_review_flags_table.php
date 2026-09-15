<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 🚩 "report this comment" action on a Review (any module — Poetry/Prose
 * is the first consumer). Deliberately its own table rather than a column on
 * `reviews` — a comment can be flagged by more than one member, so this
 * mirrors `reactions`' one-row-per-user shape rather than a single
 * flagged_at/flagged_by pair. No `reason` column: the feature is a single
 * 🚩 click, not a report form. The unique index is both "one flag per user
 * per comment" and the action's own backstop against a double-click/retry
 * race creating two rows (see App\Actions\Review\FlagReviewAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_flags');
    }
};
