<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-confirmed reversal (2026-09-02): Inspirational Resources gains a
 * public listing + per-submission detail page for Approved submissions
 * (mirroring Poetry/Prose's landing page, minus the hero banner) — a slug
 * is needed for that detail page's URL. Generated once at submission time
 * (CreateResourceSubmissionAction), never user-supplied. Nullable/unique
 * rather than NOT NULL so no backfill is required for any pre-existing row
 * — this feature is brand new, so in practice no row predates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
