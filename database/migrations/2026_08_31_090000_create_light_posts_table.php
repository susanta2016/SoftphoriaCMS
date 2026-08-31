<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member-authored short public message — first captured by the
 * registration page's "Leave a Little Light" prompt. Deliberately its own
 * table (not a column on users) so the future Light Post landing/detail
 * pages extend a real, dedicated model rather than migrating data off
 * users later. is_public defaults false at the database level; only the
 * registration flow explicitly sets it true, since that's the one flow
 * that has told the visitor up front the post will be shared publicly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('light_posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['is_public', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('light_posts');
    }
};
