<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only — `resource_submissions` (2026_08_10_100903) already links
 * a submission to what it was published as (`inspirational_resource_id`),
 * but never to what it was drafted into as a full Poetry/Prose entry. Same
 * nullable/nullOnDelete shape as the existing sibling column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->foreignId('poetry_prose_id')->nullable()->after('inspirational_resource_id')
                ->constrained('poetry_prose')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poetry_prose_id');
        });
    }
};
