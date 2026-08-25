<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only — `poetry_prose` (2026_08_10_100801) never carried a
 * content-type column. Client-confirmed: the five content types (Essay,
 * Reflection, Hymn, Poetry, Article) are a fixed, closed enum, stored here
 * rather than folded into the existing freeform `Category` taxonomy
 * (poetry_prose_categories), which remains a separate admin-managed layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poetry_prose', function (Blueprint $table) {
            $table->string('content_type')->default('poetry')->after('body')->index();
        });
    }

    public function down(): void
    {
        Schema::table('poetry_prose', function (Blueprint $table) {
            $table->dropColumn('content_type');
        });
    }
};
