<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: turns the `user_downloads` table — created in the original
 * Phase-1 schema pass (2026_08_10_100202) but never wired to any code path,
 * confirmed by grep before writing this migration — into the real download
 * audit trail: purchaser (registered or guest, via entitlement_id),
 * album/single/track, which grant authorized it, timestamp, success/failure
 * and a denial reason. See App\Modules\Commerce\Models\DownloadLog (formerly
 * App\Models\UserDownload — moved, table name unchanged).
 *
 * Dropped and recreated rather than altered: several existing columns need a
 * type change (user_id NOT NULL → nullable, media_id NOT NULL → nullable),
 * which needs doctrine/dbal for Blueprint::change() — a new dependency this
 * task has no other reason to add. Safe because the table is confirmed
 * unused: no code anywhere writes to it yet, so there is no data to lose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_downloads');

        Schema::create('user_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entitlement_id')->nullable()->constrained('entitlements')->nullOnDelete();
            $table->string('access_type')->nullable();
            $table->foreignId('track_id')->constrained('tracks')->restrictOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->string('status')->default('succeeded');
            $table->string('denial_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'track_id']);
            $table->index('entitlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_downloads');

        Schema::create('user_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'media_id']);
        });
    }
};
