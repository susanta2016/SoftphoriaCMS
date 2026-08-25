<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed (final): one collection per Poetry/Prose entry via a
 * simple belongsTo — not the many-to-many poetry_prose_collection_items
 * pivot the schema also happens to provision. That pivot table is left in
 * place, unused, rather than dropped — the same "don't destructively alter
 * pre-existing schema, just don't wire it" handling already applied to
 * inspirational_resources/resource_tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poetry_prose', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('content_type')
                ->constrained('poetry_prose_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('poetry_prose', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
        });
    }
};
