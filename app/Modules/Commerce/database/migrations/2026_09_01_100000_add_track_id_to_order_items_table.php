<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends order_items' "exactly one purchasable" pattern from two options
 * (Album, Single) to three (Album, Single, Track) — a Single already grants
 * exactly one track by construction (Single hasOne Track), but an
 * Album-owned track has no purchasable unit of its own until now: buying it
 * individually must never mean buying the whole Album. track_id is a plain
 * additional nullable FK, matching album_id/single_id's exact shape — no
 * pricing field, no new source of truth (unit_price is still always
 * GlobalPricingResolver::perSongPrice() snapshotted at purchase time, exactly
 * like a Single). See App\Shared\Concerns\BelongsToExactlyOneOf, generalized
 * in the same change to support three columns instead of two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->after('single_id')->constrained('tracks')->restrictOnDelete();
            $table->unique(['order_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'track_id']);
            $table->dropConstrainedForeignId('track_id');
        });
    }
};
