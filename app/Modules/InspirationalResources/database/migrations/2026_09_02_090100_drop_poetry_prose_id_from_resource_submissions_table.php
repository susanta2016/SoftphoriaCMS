<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed reversal (2026-09-02): the "Create Poetry/Prose Draft"
 * editorial conversion is removed — Inspirational Resources submissions no
 * longer relate to Poetry/Prose at all, so the link column added in
 * 2026_08_26_100001_add_poetry_prose_id_to_resource_submissions_table is
 * dropped here rather than left dormant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poetry_prose_id');
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->foreignId('poetry_prose_id')->nullable()->after('inspirational_resource_id')
                ->constrained('poetry_prose')->nullOnDelete();
        });
    }
};
