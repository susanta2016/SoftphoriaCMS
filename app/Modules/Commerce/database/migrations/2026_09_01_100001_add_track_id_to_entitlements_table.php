<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Entitlement-side counterpart of order_items' new track_id — see that
 * migration's docblock. A track_id entitlement covers exactly the one Track
 * it names (Entitlement::coversTrack()), never the tracks of whatever Album
 * it happens to belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->after('single_id')->constrained('tracks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('track_id');
        });
    }
};
