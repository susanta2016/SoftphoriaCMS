<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A minimal, generic 🙌 reaction — deliberately separate from `reviews`
 * (client-confirmed, 2026-09-02: a reaction and a comment are independent
 * member actions, never the same submission, and a reaction is never a
 * repurposed star rating). No `type`/emoji column: exactly one reaction
 * exists today (🙌), so a type column would be unused complexity until a
 * second reaction is ever requested. The unique index is both "one
 * reaction per user per item" and the toggle mechanism's own backstop
 * against a double-click/retry race creating two rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reactable_type', 'reactable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
