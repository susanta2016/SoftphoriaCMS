<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the discriminator that lets a single light_posts row be either the
 * existing registration-time "Leave a Little Light" post or a new Gratitude
 * Journal entry (Gratitude Journal audit §3/§13: reuse light_posts, do not
 * create a second table). Defaulting the column to 'registration' backfills
 * every existing row automatically — every row in this table today came
 * from CreatesLightPostOnRegistration, so this default is also correct data,
 * not just a placeholder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('light_posts', function (Blueprint $table) {
            $table->string('source')->default('registration')->after('user_id');
            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('light_posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
