<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A full-album purchase is downloaded only as one ZIP (client-confirmed
 * 2026-10-29) — its entitlement no longer authorizes individual track
 * downloads at all. zip_downloads_used is that entitlement's one enforced
 * counter, limited by the same snapshotted max_downloads.
 *
 * Kept separate from downloads_used rather than reusing it: on existing
 * Album entitlements downloads_used holds the old pooled count of
 * individual track downloads (the bug that locked a guest out after 5
 * downloads across 4 tracks). That history is left untouched; it just stops
 * being the Album's enforced counter, so no purchaser is left locked out of
 * the ZIP by downloads of a different kind, and nothing is reset.
 *
 * Single/Track (individual song) entitlements are unaffected: one
 * entitlement per purchased song, each enforcing its own downloads_used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->unsignedInteger('zip_downloads_used')->default(0)->after('downloads_used');
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropColumn('zip_downloads_used');
        });
    }
};
